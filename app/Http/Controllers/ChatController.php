<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Services\Itinerary\TravelAssistantService;
use App\Services\Itinerary\TravelProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    // ==========================================
    // Bắt đầu phiên chat mới
    // POST /api/chat/start
    // ==========================================
    public function startSession(Request $request)
    {
        if ($request->hasHeader('Idempotency-Key')) {
            $key = $request->header('Idempotency-Key');
            abort_unless(is_string($key) && strlen($key) >= 8 && strlen($key) <= 100, 422, 'Idempotency-Key không hợp lệ.');
            $result = app(\App\Services\Itinerary\IdempotentWriter::class)->run($request->user(), hash('sha256', 'chat:start:'.$key), 'chat:start', [], function() use ($request) {
                $session = ChatSession::create(['customer_id'=>$request->user()->id,'session_token'=>Str::random(32),
                    'title'=>'Cuộc trò chuyện mới','context_json'=>[],'status'=>'active','started_at'=>now()]);
                return ['status'=>201,'body'=>['message'=>'Bắt đầu phiên chat mới','session_id'=>$session->id,'session_token'=>$session->session_token]];
            });
            return response()->json($result['body'], $result['status']);
        }
        $customerId = auth('sanctum')->id();

        // Tạo session token random — dùng để identify phiên chat
        $sessionToken = Str::random(32);

        $session = ChatSession::create([
            'customer_id'   => $customerId,
            'session_token' => $sessionToken,
            'title'         => 'Cuộc trò chuyện mới',
            'context_json'  => [], // Bộ nhớ bot ban đầu rỗng
            'status'        => 'active',
            'started_at'    => now(),
        ]);

        return response()->json([
            'message'       => 'Bắt đầu phiên chat mới',
            'session_id'    => $session->id,
            'session_token' => $sessionToken,
        ], 201);
    }

    // ==========================================
    // Gửi tin nhắn và nhận phản hồi từ AI
    // POST /api/chat/{session_id}/message
    // ==========================================
    public function sendMessage(Request $request, $session_id)
    {
        $data = $request->validate(['message' => 'required|string|max:2000', 'planner_context' => 'sometimes|array']);
        $plannerContext = null;
        if (isset($data['planner_context'])) {
            $plannerContext = \Illuminate\Support\Facades\Validator::make($data['planner_context'], \App\Http\Requests\Itinerary\PlannerRequest::tripRules())->validate();
            abort_unless(isset($plannerContext['days']) && array_is_list($plannerContext['days']) && count($plannerContext['days']) === $plannerContext['duration_days'], 422, 'Lịch đang chỉnh chưa khớp số ngày.');
            abort_if($plannerContext['stay_mode'] === 'day_trip' && $plannerContext['duration_days'] !== 1, 422, 'Đi trong ngày chỉ có 1 ngày.');
        }
        $key = $request->header('Idempotency-Key', Str::uuid()->toString());
        abort_unless(is_string($key) && strlen($key) >= 8 && strlen($key) <= 100, 422, 'Idempotency-Key không hợp lệ.');
        if(str_contains($request->header('Accept',''),'text/event-stream'))return $this->streamMessage($request,(int)$session_id,$data['message'],$key,$plannerContext);
        try {
            return response()->json(app(TravelAssistantService::class)->turn($request->user(), (int)$session_id, $data['message'], $key, null, $plannerContext));
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $error) {
            $body = json_decode($error->getResponse()->getContent(), true);
            if (isset($body['code']) && $error->getResponse()->getStatusCode() >= 500) return $this->aiUnavailable($body['message'], $body['code']);
            throw $error;
        }
    }

    private function streamMessage(Request $request,int $sessionId,string $message,string $key,?array $plannerContext = null)
    {
        $customer=$request->user();
        abort_if(\App\Support\CustomerSessions::isAdminToken($customer,$customer->currentAccessToken()),403);
        ChatSession::where('customer_id',$customer->id)->findOrFail($sessionId);
        $requestId=$request->header('X-Aurel-Request-Id',Str::uuid()->toString());
        abort_unless(is_string($requestId) && preg_match('/^[a-zA-Z0-9_-]{8,100}$/D',$requestId),422,'Mã yêu cầu không hợp lệ.');
        return response()->stream(function()use($customer,$sessionId,$message,$key,$requestId,$plannerContext){
            // Finish the current provider call on disconnect, then check cancellation before further work/commit.
            $previousIgnore=ignore_user_abort(true);
            if(PHP_SAPI!=='cli'){@set_time_limit(300);@ini_set('zlib.output_compression','0');}
            $progress=new TravelProgress($requestId,function(string $event,array $payload){
                echo 'event: '.$event."\n".'data: '.json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n\n";
                if(PHP_SAPI!=='cli'){if(ob_get_level()>0)@ob_flush();flush();}
            });
            try {
                $progress->emit('processing_request');
                $body=app(TravelAssistantService::class)->turn($customer,$sessionId,$message,$key,$progress,$plannerContext);
                $progress->result($body);
            }catch(\Illuminate\Http\Exceptions\HttpResponseException $error){
                $body=json_decode($error->getResponse()->getContent(),true);
                $progress->error($body['message'] ?? 'Chưa xử lý được yêu cầu. Bạn thử lại nhé.',$body['code'] ?? 'assistant_unavailable');
            }catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error){
                $status=$error->getStatusCode();
                $text=match($status){404=>'Không tìm thấy cuộc trò chuyện của bạn.',403=>'Bạn không có quyền thực hiện thao tác này.',
                    409=>'Cuộc trò chuyện hoặc lịch trình đã thay đổi, hoặc tin nhắn trước đang được xử lý. Bạn chờ một chút rồi thử lại nhé.',
                    422=>'Thông tin yêu cầu chưa phù hợp. Bạn kiểm tra lại giúp mình nhé.',default=>'Chưa xử lý được yêu cầu. Bạn thử lại nhé.'};
                $progress->error($text,'assistant_request_'.$status,!in_array($status,[403,404],true));
            }catch(\Throwable $error){
                // No raw provider errors, URLs, SQL, prompts or stack traces are sent through progress.
                $progress->error('Chưa xử lý được yêu cầu lúc này. Bạn thử lại nhé.','assistant_unavailable');
            }finally{ignore_user_abort((bool)$previousIgnore);}
        },200,['Content-Type'=>'text/event-stream; charset=UTF-8','Cache-Control'=>'no-cache, no-store','X-Accel-Buffering'=>'no']);
    }

    private function aiUnavailable(string $message, string $code)
    {
        // Preserve the existing Chat response contract used by both Customer widgets.
        return response()->json(['message' => $message, 'code' => $code], 200);
    }

    public function routeDay(Request $request,int $session_id,int $message_id,int $day)
    {
        abort_if(\App\Support\CustomerSessions::isAdminToken($request->user(),$request->user()->currentAccessToken()),403);
        ChatSession::where('customer_id',$request->user()->id)->findOrFail($session_id);
        $message=ChatMessage::where('session_id',$session_id)->where('role','bot')->findOrFail($message_id);
        $assistant=$message->entities_json['assistant'] ?? [];
        $timeline=collect($assistant['components'] ?? [])->firstWhere('type','timeline');
        $selected=collect($timeline['days'] ?? [])->firstWhere('day_number',$day);abort_unless($selected,404);
        $locationId=$assistant['state']['location_id'] ?? null;
        abort_unless(is_int($locationId)&&app(\App\Services\Itinerary\DestinationService::class)->supported($locationId),422,'Địa phương của lịch này chưa được hỗ trợ.');
        $geo=app(\App\Services\Itinerary\GeoapifyClient::class);
        $items=array_values(array_filter($selected['items'],fn($item)=>isset($item['coordinates'])&&is_array($item['coordinates'])));
        $points=array_column($items,'coordinates');abort_unless($points&&count($points)<=15,422,'Chưa có đủ tọa độ để mở tuyến đường. Bạn có thể mở liên kết Google Maps của từng địa điểm.');
        $sheet=app(\App\Services\Places\SheetPlaceCatalog::class);
        if($sheet->isPrimary($locationId)){
            foreach($items as $item){
                $source=$sheet->find($locationId,(int)($item['source_place_id'] ?? 0));
                abort_unless($source && $source['coordinates']==$item['coordinates'],422,'Vị trí nguồn đã thay đổi hoặc chưa được phép gợi ý. Hãy tạo lại đề xuất.');
            }
        }else{
            $region=$geo->region(\App\Models\Location::findOrFail($locationId));$boundary=$geo->boundary($region);
            foreach($points as $point)abort_unless($geo->contains($boundary,$point),422,'Vị trí không thuộc địa phương của lịch trình.');
        }
        $mode=$timeline['transport'] ?? collect($items)->pluck('travel_from_previous.mode')->filter()->first();
        return response()->json(count($points)===1?['geometry'=>null,'attribution'=>'© OpenStreetMap contributors']:$geo->route($points,(string)$mode));
    }

    // ==========================================
    // Xem lịch sử chat của 1 session
    // GET /api/chat/{session_id}
    // ==========================================
    public function getSession($session_id)
    {
        $session = ChatSession::with(['messages' => function ($q) {
            $q->orderBy('id', 'asc');
        }])
        ->where('customer_id', auth('sanctum')->id())
        ->findOrFail($session_id);

        foreach($session->messages as $message){
            $entities=$message->entities_json ?? [];$assistant=$entities['assistant'] ?? []; $removed=false;
            foreach($assistant['components'] ?? [] as $index=>$component)if(($component['type'] ?? '')==='places'){
                $fresh=app(\App\Services\Places\SheetPlaceCatalog::class)->refreshPlaces($component['items'] ?? [],$assistant['state']['location_id'] ?? null);
                $removed=$removed || count($fresh)!==count($component['items'] ?? []);$entities['assistant']['components'][$index]['items']=$fresh;
            }
            if($removed)$message->content='Một địa điểm trong gợi ý cũ đã bị ngừng gợi ý. Bạn hãy gửi yêu cầu mới để chọn địa điểm khác.';
            $message->entities_json=$entities; // Response only; stored conversation history is preserved.
        }
        return response()->json($session);
    }

    // ==========================================
    // Xem tất cả phiên chat của user
    // GET /api/chat
    // ==========================================
    public function mySessions(Request $request)
    {
        $data=$request->validate(['status'=>'sometimes|in:active,closed']);
        $sessions = ChatSession::where('customer_id', auth('sanctum')->id())
            ->when(isset($data['status']),fn($query)=>$query->where('status',$data['status']))
            ->orderBy('started_at', 'desc')
            ->limit(100)->get(['id','title','status','started_at','ended_at']);

        return response()->json($sessions);
    }

    // ==========================================
    // Đóng phiên chat
    // PUT /api/chat/{session_id}/close
    // ==========================================
    public function closeSession(Request $request,$session_id)
    {
        abort_if(\App\Support\CustomerSessions::isAdminToken($request->user(),$request->user()->currentAccessToken()),403);
        $session=ChatSession::where('customer_id',$request->user()->id)->findOrFail($session_id);
        $lock=\Illuminate\Support\Facades\Cache::lock('aurel:chat:'.$session->id,600);
        abort_unless($lock->get(),409,'AI đang trả lời. Bạn chờ phản hồi hoàn tất rồi đóng hội thoại nhé.');
        try {
            $session->refresh();
            if($session->status!=='closed')$session->forceFill(['status'=>'closed','ended_at'=>now()])->save();
            return response()->json(['message'=>'Phiên chat đã đóng','session_id'=>$session->id,'status'=>'closed']);
        }finally{$lock->release();}
    }

    public function destroySession(Request $request,int $session_id)
    {
        abort_if(\App\Support\CustomerSessions::isAdminToken($request->user(),$request->user()->currentAccessToken()),403);
        ChatSession::where('customer_id',$request->user()->id)->findOrFail($session_id);
        $lock=\Illuminate\Support\Facades\Cache::lock('aurel:chat:'.$session_id,600);
        abort_unless($lock->get(),409,'AI đang trả lời. Bạn chờ phản hồi hoàn tất rồi xóa hội thoại nhé.');
        try {
            \Illuminate\Support\Facades\DB::transaction(function()use($request,$session_id){
                \App\Models\Customer::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $session=ChatSession::where('customer_id',$request->user()->id)->lockForUpdate()->findOrFail($session_id);
                if(\Illuminate\Support\Facades\Schema::hasTable('itinerary_write_request')) {
                    // Remove replay copies of this conversation too; unrelated trip requests stay intact.
                    \Illuminate\Support\Facades\DB::table('itinerary_write_request')->where('customer_id',$request->user()->id)
                        ->where(function($query)use($session_id){$query->where('operation','chat:'.$session_id)
                            ->orWhere(fn($start)=>$start->where('operation','chat:start')->where('response_body->session_id',$session_id));})->delete();
                }
                $session->messages()->delete();
                $session->delete();
            },3);
            return response()->json(['message'=>'Hội thoại đã xóa','session_id'=>$session_id]);
        }finally{$lock->release();}
    }
}
