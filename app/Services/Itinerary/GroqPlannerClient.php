<?php

namespace App\Services\Itinerary;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GroqPlannerClient
{
    public function send(array $request,?TravelProgress $progress,bool &$quotaRetried): Response
    {
        $key=config('services.groq.api_key');
        if(!is_string($key) || trim($key)==='')$this->fail('Chưa cấu hình dịch vụ tạo lịch trình AI.','groq_key_missing');
        do{
            $progress?->planning();
            try{$response=Http::acceptJson()->withToken($key)->connectTimeout(5)->timeout(40)->withOptions(['allow_redirects'=>false])->post('https://api.groq.com/openai/v1/chat/completions',$request);}
            catch(ConnectionException $error){$this->fail('Chưa kết nối được AI. Vui lòng thử lại.','groq_unreachable');}
            $delay=$response->header('retry-after');
            if($response->status()!==429 || $quotaRetried || !is_numeric($delay) || (float)$delay<=0 || (float)$delay>45)break;
            $quotaRetried=true;$seconds=min(45,(int)ceil((float)$delay)+1);
            Log::notice('Itinerary AI waiting for quota',['model'=>$request['model'],'retry_after_seconds'=>$seconds]);
            for($i=0;$i<$seconds;$i++){$progress?->emit('processing_request');sleep(1);}
        }while(true);
        if($response->successful())return $response;
        $code=match($response->status()){401,403=>'groq_access_denied',404=>'groq_model_unavailable',429=>'groq_quota',default=>'groq_unavailable'};
        if(in_array($response->json('error.code'),['model_decommissioned','json_validate_failed','model_not_found'],true))$code='groq_'.$response->json('error.code');
        $message=match($code){'groq_quota'=>'AI đã đạt hạn mức. Vui lòng thử lại sau.','groq_model_unavailable','groq_model_decommissioned','groq_model_not_found'=>'Mô hình AI hiện chưa khả dụng. Vui lòng thử lại sau.',default=>'Dịch vụ AI chưa sẵn sàng. Vui lòng thử lại.'};
        $this->fail($message,$code,$response->status());
    }

    private function fail(string $message,string $code,?int $providerStatus=null): never
    {
        app(GeoapifyClient::class)->unavailable($message,$code,503,$providerStatus);
    }
}
