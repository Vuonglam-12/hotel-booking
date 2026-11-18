<?php
// Exercise the actual backend calendar engine in Chrome's isolated fixture server. No app/.env/DB.
require dirname(__DIR__,2).'/vendor/autoload.php';
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$schedule=['version'=>1,'timezone'=>'Asia/Ho_Chi_Minh','weekly'=>[
    1=>['state'=>'open','intervals'=>[['start'=>'08:00','end'=>'12:00','end_day_offset'=>0],['start'=>'14:00','end'=>'18:00','end_day_offset'=>0]]],
    5=>['state'=>'open','intervals'=>[['start'=>'22:00','end'=>'02:00','end_day_offset'=>1]]],
    6=>['state'=>'open','intervals'=>[['start'=>'22:00','end'=>'02:00','end_day_offset'=>1]]],7=>['state'=>'closed','intervals'=>[]]],
    'exceptions'=>['2026-11-10'=>['state'=>'closed','intervals'=>[]],'2026-11-15'=>['state'=>'open','intervals'=>[['start'=>'10:00','end'=>'16:00','end_day_offset'=>0]]]]];
$schedule['weekly'][1]['source_url']='https://example.invalid/opening-hours';$schedule['weekly'][1]['checked_at']='2026-10-31';
$engine=new App\Services\Places\OpeningHours;
$check=$engine->check($schedule,$input['date'] ?? null,$input['start_time'] ?? null,$input['end_time'] ?? null,(int)($input['end_day_offset'] ?? 0));
$check['display']=$engine->display($schedule,null,new Carbon\CarbonImmutable('2026-11-09 11:00','Asia/Ho_Chi_Minh'));
echo json_encode($check,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
