<?php

// Reuse the existing owned-server/schema/fixture setup. Never bootstrap the project's environment.
require dirname(__DIR__).'/tests/Support/prepare_roomtype_v2_staging.php';
$path=$runtime.'/staging-session.json';
$descriptor['booking_demo']=true;
file_put_contents($path,json_encode($descriptor,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
file_put_contents($runtime.'/demo-access.txt',"AUREL DEMO ONLY - synthetic web accounts\r\nCustomer: ".$descriptor['customer_email']."\r\nAdmin: ".$descriptor['admin_email']."\r\nPassword: ".$descriptor['password']."\r\nNo production credentials.\r\n");
