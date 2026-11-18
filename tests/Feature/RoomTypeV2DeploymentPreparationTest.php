<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Support\RoomTypeV2PreflightApproval;
use App\Support\RoomTypeV2DatabasePrivileges;

class RoomTypeV2DeploymentPreparationTest extends TestCase
{
    public function test_preflight_approval_rejects_missing_stale_mismatched_root_and_remote_insecure_targets_without_database(): void
    {
        $s=\App\Support\RoomTypeV2TestDatabase::settings();
        $a=['approved_read_only'=>true,'action'=>'roomtype_v2_preflight','expires_at'=>gmdate('Y-m-d\TH:i:s\+00:00',time()+300),
            'server_uuid'=>$s['server_uuid'],'target'=>array_intersect_key($s,array_flip(['host','port','database','username','password']))];
        $confirm=$s['host'].':'.$s['port'].'/'.$s['database'];
        \Illuminate\Support\Facades\DB::shouldReceive('connection')->never();
        $this->assertSame($a['target'],RoomTypeV2PreflightApproval::validate($a,$confirm));
        foreach ([['approved_read_only'=>false],['expires_at'=>gmdate('Y-m-d\TH:i:s\+00:00',time()-1)],['server_uuid'=>'invalid'],
            ['target'=>array_replace($a['target'],['username'=>'root'])],['target'=>array_replace($a['target'],['database'=>'hotel_booking'])],
            ['target'=>array_replace($a['target'],['port'=>-1])],['target'=>array_replace($a['target'],['host'=>'remote.example.test'])]] as $delta) {
            try { RoomTypeV2PreflightApproval::validate(array_replace($a,$delta),$confirm); $this->fail('Invalid approval refused before PDO.'); }
            catch (\RuntimeException $e) { $this->assertStringContainsString('connection',$e->getMessage()); }
        }
    }
    public function test_schema_privileges_reject_wildcards_roles_global_file_and_cross_schema(): void
    {
        $user=" TO `fixture`@`127.0.0.1`"; $target='aurel_owned';
        RoomTypeV2DatabasePrivileges::assert(['GRANT SELECT ON `aurel\\_owned`.*'.$user],$target,['SELECT'],false);
        RoomTypeV2DatabasePrivileges::assert(['GRANT SELECT ON `aurel_owned`.*'.$user],$target,['SELECT'],true);
        $this->addToAssertionCount(2);
        foreach (['GRANT SELECT ON `aurel_owned`.*','GRANT SELECT ON *.*','GRANT FILE ON *.*','GRANT SELECT ON `other`.*',
            'GRANT SELECT, INSERT ON `aurel\\_owned`.*','GRANT `privileged_role`','GRANT SELECT ON `aurel\\_owned`.* TO `fixture`@`127.0.0.1` WITH GRANT OPTION'] as $grant) {
            try { RoomTypeV2DatabasePrivileges::assert([$grant.$user],$target,['SELECT'],false); $this->fail('Unsafe privilege refused.'); }
            catch (\RuntimeException $e) { $this->assertNotSame('',$e->getMessage()); }
        }
    }
    public function test_debug_mode_and_unapproved_preflight_are_refused_before_connection(): void
    {
        config(['app.debug'=>true]); \Illuminate\Support\Facades\DB::shouldReceive('connection')->never();
        try { \App\Support\RoomTypeV2ProductionGuard::connection('mysql'); $this->fail(); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('debug disabled',$e->getMessage()); }
        $p=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tools/roomtype-v2-preflight.php'),'--output='.storage_path('app/private/phase2d-evidence/unapproved-preflight.json')],base_path());
        $this->assertNotSame(0,$p->run()); $this->assertStringContainsString('no connection opened',$p->getErrorOutput().$p->getOutput());
        $this->assertFileDoesNotExist(storage_path('app/private/phase2d-evidence/unapproved-preflight.json'));
    }

    public function test_offline_asset_inventory_refuses_public_and_existing_outputs(): void
    {
        $public=public_path('__phase2d_refused_inventory.json');
        $settings=\App\Support\RoomTypeV2TestDatabase::settings();
        $existing=storage_path('app/private/roomtype-v2-tests/'.$settings['run_id'].'/inventory-output-refusal-'.bin2hex(random_bytes(6)).'.json');
        file_put_contents($existing,'{"synthetic":true}'); $before=hash_file('sha256',$existing);
        try {
            foreach ([$public,$existing] as $output) {
                $p=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tools/roomtype-v2-asset-inventory.php'),'--output='.$output],base_path());
                $this->assertNotSame(0,$p->run());
                $this->assertStringContainsString('private storage',$p->getErrorOutput().$p->getOutput());
            }
            $this->assertFileDoesNotExist($public); $this->assertSame($before,hash_file('sha256',$existing));
        } finally { unlink($existing); }
    }
}
