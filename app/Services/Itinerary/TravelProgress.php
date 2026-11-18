<?php

namespace App\Services\Itinerary;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Ephemeral, request-local progress. Never contains prompts, model reasoning or conversation content. */
final class TravelProgress
{
    private Closure $sink;
    private Closure $clock;
    private Closure $cancelled;
    private float $started;
    private int $sequence = 0;
    private bool $finished = false;
    private ?array $last = null;
    private float $lastSent = 0;
    private ?int $day = null;
    private const CODES = ['considering','processing_request','reading_context','searching_places','searching_images',
        'searching_hotels','checking_rooms','calculating_route','creating_itinerary','revising_itinerary','revising_day','checking_itinerary'];

    public function __construct(private string $requestId, callable $sink, private int $timeoutMs = 240000,
        ?callable $cancelled = null, ?callable $clock = null)
    {
        $this->sink=Closure::fromCallable($sink);
        $this->clock=Closure::fromCallable($clock ?? fn()=>hrtime(true)/1e6);
        $this->cancelled=Closure::fromCallable($cancelled ?? fn()=>connection_aborted()!==0);
        $this->started=($this->clock)();
    }

    public function emit(string $code, array $parameters = []): void
    {
        $this->assertRunning();
        if(!in_array($code,self::CODES,true))$code='considering';
        // Only a validated calendar day can be sent; no arbitrary text from a model or exception.
        $parameters=$code==='revising_day' && is_int($parameters['day'] ?? null) && $parameters['day']>=1 && $parameters['day']<=30
            ? ['day'=>$parameters['day']] : [];
        if($code==='revising_day' && !$parameters)$code='considering';
        $event=['code'=>$code,...$parameters];
        $now=($this->clock)();
        if($event===$this->last && $now-$this->lastSent<10000)return;
        $this->last=$event;$this->lastSent=$now;$this->send('progress',$event);
    }

    public function planning(?int $day = null): void
    {
        if($day !== null)$this->day=$day;
        $this->emit($this->day===null?'creating_itinerary':'revising_day',$this->day===null?[]:['day'=>$this->day]);
    }

    public function assertRunning(): void
    {
        if($this->finished || ($this->cancelled)())$this->fail('Yêu cầu đã ngắt kết nối. Bạn có thể xem lại cuộc trò chuyện trước khi thử lại.','request_cancelled');
        if(($this->clock)()-$this->started >= $this->timeoutMs)$this->fail('Yêu cầu mất nhiều thời gian hơn dự kiến. Bạn có thể thử lại; hệ thống sẽ tránh tạo lịch trùng.','request_timeout');
    }

    public function result(array $body): void
    {
        if($this->finished)return;
        $this->finished=true;$this->send('result',['result'=>$body]);
    }

    public function error(string $message,string $code,bool $retryable = true): void
    {
        if($this->finished)return;
        $this->finished=true;$this->send('error',['message'=>$message,'code'=>$code,'retryable'=>$retryable]);
    }

    private function send(string $event,array $data): void
    {
        ($this->sink)($event,['request_id'=>$this->requestId,'sequence'=>++$this->sequence,...$data]);
    }

    private function fail(string $message,string $code): never
    {
        throw new HttpResponseException(response()->json(['message'=>$message,'code'=>$code],408));
    }
}
