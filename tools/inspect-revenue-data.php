<?php
// Read only: inspect report schema and totals; never emit credentials or customer information.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection();
$db->beforeExecuting(function($sql){if(!preg_match('/^\s*(select|show|explain)\b/i',$sql))throw new RuntimeException('Read-only revenue inspection.');});
$tables=[];
foreach(['payment','booking','booking_room','booking_nightly_rate','invoice','invoice_item','service','booking_service','booking_demo','booking_demo_reconciliation','hotel_room_type'] as $table){
    if($db->getSchemaBuilder()->hasTable($table))$tables[$table]=$db->getSchemaBuilder()->getColumnListing($table);
}
echo json_encode(['clock'=>$db->selectOne('SELECT NOW() db_clock, UTC_TIMESTAMP() utc_clock, @@session.time_zone session_timezone, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) offset_seconds'),
    'tables'=>$tables,'payments'=>$db->table('payment')->selectRaw('payment_status, payment_method, COUNT(*) count, SUM(amount) total')->groupBy('payment_status','payment_method')->get(),
    'sample'=>$db->table('payment as p')->join('booking as b','b.id','=','p.booking_id')->orderByDesc('p.id')->limit(5)
        ->get(['p.id','p.booking_id','p.payment_status','p.payment_method','p.amount','p.paid_at','b.status as booking_status','b.total_price'])],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
