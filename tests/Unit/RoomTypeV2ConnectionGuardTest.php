<?php

namespace Tests\Unit;

use App\Support\RoomTypeV2TestDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RoomTypeV2ConnectionGuardTest extends TestCase
{
    #[DataProvider('forbiddenConfigurations')]
    public function test_invalid_configuration_is_refused_before_any_connection(array $changes, bool $testing = true): void
    {
        $settings = RoomTypeV2TestDatabase::settings();
        $config = array_merge($settings, ['driver' => 'mysql'], $changes);
        $opened = false;
        try {
            RoomTypeV2TestDatabase::validate($config, $testing);
            $opened = true; // A connector would only be reached after this point.
            $this->fail('Forbidden config passed preconnection guard.');
        } catch (\RuntimeException $error) {
            $this->assertFalse($opened);
            $this->assertNotEmpty($error->getMessage());
        }
    }

    public static function forbiddenConfigurations(): array
    {
        return [
            'primary database' => [['database' => 'hotel_booking']],
            'primary port' => [['port' => 3306]],
            'malformed port' => [['port' => '58065.9']],
            'out of range port' => [['port' => 65536]],
            'non-testing app' => [[], false],
            'root account' => [['username' => 'root']],
            'remote host' => [['host' => 'example.com']],
            'socket escape' => [['unix_socket' => 'application.sock']],
            'URL override' => [['url' => 'mysql://root@127.0.0.1/hotel_booking']],
            'read/write override' => [['write' => ['database' => 'hotel_booking']]],
            'unguarded driver' => [['driver' => 'sqlite']],
            'not-owned schema' => [['database' => 'aurel_roomtype_v2_test_ffffffffffff']],
        ];
    }

    public function test_owned_test_configuration_is_accepted_without_opening_a_connection(): void
    {
        RoomTypeV2TestDatabase::validate(array_merge(RoomTypeV2TestDatabase::settings(), ['driver' => 'mysql']), true);
        $this->addToAssertionCount(1);
    }
}
