<?php

namespace App\Services\Places;

use Illuminate\Support\Facades\Http;

/** Read-only service-account access. Credentials and tokens never leave this server. */
final class SheetsReader
{
    public const SCOPE='https://www.googleapis.com/auth/spreadsheets.readonly';
    private ?string $token=null;

    public function read(?array $source=null): array
    {
        $source ??= app(SheetPlaceSources::class)->all()[0];
        $spreadsheetId=$source['spreadsheet_id'] ?? null;
        // The guide is deliberately fetched before any data is interpreted.
        $titles=$this->tabTitles($spreadsheetId);
        $guideExists=in_array($source['guide_tab'],$titles,true);
        $guide=$guideExists?$this->values($source['guide_tab'],null,false,$spreadsheetId):[];
        $dataTab=$source['data_tab'];
        if(!in_array($dataTab,$titles,true)){
            $aliases=array_values(array_filter($titles,fn($title)=>SheetPlaceMapper::normalize($title)===SheetPlaceMapper::normalize($source['location_name'])));
            if(count($aliases)!==1)throw new \RuntimeException('Không tìm thấy duy nhất tab địa điểm cho '.$source['location_name'].'. Dữ liệu đã đồng bộ được giữ nguyên.');
            $dataTab=$aliases[0];
        }
        // Read the whole used tab, never cap the number of rows or silently read only A1.
        $data=$this->values($dataTab,null,false,$spreadsheetId);
        $hoursTab=$source['hours_tab'];$hours=null;$hoursState='not_configured';
        if($hoursTab){
            $hoursState=in_array($hoursTab,$titles,true)?'read':'missing';
            if($hoursState==='read')$hours=$this->values($hoursTab,null,true,$spreadsheetId);
        }
        return ['guide'=>$guide,'guide_state'=>$guideExists?'read':'missing','data'=>$data,'hours'=>$hours,'hours_state'=>$hoursState,'data_tab'=>$dataTab];
    }

    private function tabTitles(?string $spreadsheetId=null): array
    {
        try {
            $response=Http::acceptJson()->withToken($this->accessToken())->connectTimeout(5)->timeout(25)->withOptions(['allow_redirects'=>false,'debug'=>false])
                ->get('https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($spreadsheetId ?? config('services.place_sheet.spreadsheet_id')),['fields'=>'sheets.properties.title']);
        }catch(\Throwable $error){throw new \RuntimeException('Không đọc được danh sách tab Sheet; giữ nguyên dữ liệu lần trước.');}
        if(!$response->successful() || !is_array($response->json('sheets')))throw new \RuntimeException('Không đọc được danh sách tab Sheet; giữ nguyên dữ liệu lần trước.');
        return array_column(array_column($response->json('sheets'),'properties'),'title');
    }

    private function values(?string $tab, ?string $range=null, bool $allowEmpty=false, ?string $spreadsheetId=null): array
    {
        $id=$spreadsheetId ?? config('services.place_sheet.spreadsheet_id');
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]+$/D',$id) || !$tab) throw new \RuntimeException('Thiếu cấu hình Google Sheet địa điểm.');
        $a1="'".str_replace("'","''",$tab)."'".($range?'!'.$range:'');
        try {
            $response=Http::acceptJson()->withToken($this->accessToken())->connectTimeout(5)->timeout(25)->withOptions(['allow_redirects'=>false,'debug'=>false])
                ->get('https://sheets.googleapis.com/v4/spreadsheets/'.$id.'/values/'.rawurlencode($a1),['valueRenderOption'=>'FORMATTED_VALUE','majorDimension'=>'ROWS']);
        } catch (\Throwable $error) { throw new \RuntimeException('Không kết nối được Google Sheets; dữ liệu đã đồng bộ được giữ nguyên.'); }
        if (!$response->successful()) throw new \RuntimeException('Google Sheets từ chối đọc dữ liệu (HTTP '.$response->status().'). Dữ liệu đã đồng bộ được giữ nguyên.');
        $body=$response->json();
        if (!is_array($body) || !is_array($body['values'] ?? null) && !($allowEmpty && !isset($body['values']))) throw new \RuntimeException('Google Sheet trống hoặc trả về dữ liệu không hợp lệ.');
        return $body['values'] ?? [];
    }

    private function accessToken(): string
    {
        if ($this->token) return $this->token;
        try {
            $path=realpath(config('services.place_sheet.credentials') ?? '');
            $project=strtolower(str_replace('\\','/',base_path())).'/';
            if (!$path || !is_file($path) || str_starts_with(strtolower(str_replace('\\','/',$path)),$project)) throw new \RuntimeException();
            $credentials=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
            if (($credentials['type'] ?? '')!=='service_account' || !filter_var($credentials['client_email'] ?? '',FILTER_VALIDATE_EMAIL)) throw new \RuntimeException();
            $encode=fn($value)=>rtrim(strtr(base64_encode($value),'+/','-_'),'=');
            $claims=['iss'=>$credentials['client_email'],'scope'=>self::SCOPE,'aud'=>'https://oauth2.googleapis.com/token','iat'=>time(),'exp'=>time()+3600];
            $jwt=$encode(json_encode(['alg'=>'RS256','typ'=>'JWT'])).'.'.$encode(json_encode($claims));
            $key=openssl_pkey_get_private($credentials['private_key'] ?? '');
            $details=$key?openssl_pkey_get_details($key):false;
            if (!$details || $details['type']!==OPENSSL_KEYTYPE_RSA || $details['bits']<2048 || !openssl_sign($jwt,$signature,$key,OPENSSL_ALGO_SHA256)) throw new \RuntimeException();
            $response=Http::asForm()->acceptJson()->connectTimeout(5)->timeout(20)->withOptions(['allow_redirects'=>false,'debug'=>false])
                ->post('https://oauth2.googleapis.com/token',['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt.'.'.$encode($signature)]);
            $token=$response->json('access_token');
            if (!$response->successful() || !is_string($token) || $token==='') throw new \RuntimeException();
            return $this->token=$token;
        } catch (\Throwable $error) { throw new \RuntimeException('Không xác thực được Service Account cho Google Sheets. Kiểm tra credentials bên ngoài project và quyền Viewer.'); }
    }
}
