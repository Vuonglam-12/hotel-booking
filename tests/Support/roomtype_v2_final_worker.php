<?php

require __DIR__.'/roomtype_v2_test_bootstrap.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Carbon\Carbon::setTestNow('2026-10-12 08:00:00');
config(['services.vnpay'=>['tmn_code'=>'TEST0001','hash_secret'=>str_repeat('synthetic-test-secret-',3),'url'=>'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html','return_url'=>'http://roomtype-v2-test.invalid/payment/result']]);
$path=realpath($argv[1] ?? ''); $runtime=dirname(realpath(getenv('AUREL_ROOMTYPE_V2_TEST_SETTINGS')));
if (!$path || dirname($path)!==$runtime || !preg_match('/^ready-worker-[a-f0-9]{12}\.json$/D',basename($path))) throw new RuntimeException('Unsafe worker path.');
$request=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
if (isset($request['now'])) Carbon\Carbon::setTestNow($request['now']);
$db=App\Support\RoomTypeV2TestDatabase::connection('mysql'); $id=(int)$db->selectOne('SELECT CONNECTION_ID() AS id')->id;
App\Support\RoomTypeV2Integration::finalize();
try {
    $life=app(App\Services\BookingLifecycleServiceV2::class);
    $result=match($request['action']) {
        'create'=>app(App\Services\BookingServiceV2::class)->create('mysql',$request['customer_id'],$request['data']),
        'cancel'=>$life->cancel('mysql',$request['id'],$request['customer_id']),
        'expire'=>$life->expire('mysql',$request['id']),
        'restore'=>$life->transition('mysql',$request['id'],'confirmed'),
        'ipn'=>app(App\Services\VnpayPaymentServiceV2::class)->receive('mysql',$request['query']),
        'deliver'=>app(App\Services\BookingEffectServiceV2::class)->deliver('mysql',$request['id'],app(App\Services\PreparedV2\BookingMailSender::class)),
        'bank'=>app(App\Services\ManualBankingPaymentServiceV2::class)->verify('mysql',$request['id'],$request['payment_id'],9004,$request['data']),
        default=>throw new RuntimeException('Unknown worker action.'),
    };
    file_put_contents($path.'.result',json_encode(['success'=>true,'connection_id'=>$id,'result'=>$result],JSON_THROW_ON_ERROR));
} catch (Illuminate\Validation\ValidationException $e) {
    file_put_contents($path.'.result',json_encode(['success'=>false,'connection_id'=>$id,'errors'=>$e->errors()],JSON_THROW_ON_ERROR));
} catch (Throwable $e) {
    file_put_contents($path.'.result',json_encode(['success'=>false,'connection_id'=>$id,'unexpected'=>get_class($e),'message'=>$e->getMessage()],JSON_THROW_ON_ERROR)); exit(2);
}
