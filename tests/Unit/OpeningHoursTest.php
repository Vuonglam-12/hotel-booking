<?php
namespace Tests\Unit;

use App\Services\Places\OpeningHours;
use App\Services\Places\OpeningScheduleMapper;
use PHPUnit\Framework\TestCase;

final class OpeningHoursTest extends TestCase
{
    private function open(string $start='08:00',string $end='18:00',int $offset=0): array {return ['state'=>'open','intervals'=>[['start'=>$start,'end'=>$end,'end_day_offset'=>$offset]],'quality'=>'reference'];}
    private function schedule(array $weekly,array $exceptions=[]): array {return ['version'=>1,'timezone'=>'Asia/Ho_Chi_Minh','weekly'=>$weekly,'exceptions'=>$exceptions];}
    public function test_sunday_closed_and_exceptions_take_priority_in_both_directions():void
    {
        $engine=new OpeningHours;$base=$this->schedule([1=>$this->open(),7=>['state'=>'closed','intervals'=>[]]]);
        $closed=$engine->check($base,'2026-11-08','09:00','10:00');$this->assertSame('closed',$closed['status']);$this->assertSame('Chủ nhật, 08/11: Nghỉ cả ngày',$closed['label']);$this->assertNotEmpty($closed['suggestions']);
        $base['exceptions']['2026-11-08']=$this->open('09:00','12:00');$this->assertSame('within_hours',$engine->check($base,'2026-11-08','09:00','10:00')['status']);
        $base['exceptions']['2026-11-09']=['state'=>'closed','intervals'=>[]];$this->assertSame('closed',$engine->check($base,'2026-11-09','09:00','10:00')['status']);
    }
    public function test_entire_visit_must_fit_a_shift_and_cannot_cross_the_lunch_break():void
    {
        $rule=$this->open('08:00','12:00');$rule['intervals'][]=['start'=>'14:00','end'=>'18:00','end_day_offset'=>0];$schedule=$this->schedule([1=>$rule]);$engine=new OpeningHours;
        $this->assertSame('within_hours',$engine->check($schedule,'2026-11-09','11:00','12:00')['status']);
        $outside=$engine->check($schedule,'2026-11-09','11:00','15:00');$this->assertSame('outside_hours',$outside['status']);$this->assertSame('14:00',$outside['suggestions'][0]['start_time']);
        $this->assertSame('14:00',$engine->nextSlot($schedule,'2026-11-09','11:30',60,'18:00'));
        $this->assertNull($engine->nextSlot($schedule,'2026-11-09','17:30',60,'18:00'));
    }
    public function test_overnight_carry_24_hours_and_date_closure():void
    {
        $engine=new OpeningHours;$schedule=$this->schedule([6=>$this->open('22:00','02:00',1)]);
        $this->assertSame('within_hours',$engine->check($schedule,'2026-11-07','23:00','01:30',1)['status']);
        $this->assertSame('within_hours',$engine->check($schedule,'2026-11-08','00:30','01:30')['status']);
        $this->assertStringContainsString('00:00–02:00 (từ ca qua đêm hôm trước)',$engine->check($schedule,'2026-11-08','00:30','01:30')['label']);
        $schedule['exceptions']['2026-11-08']=['state'=>'closed','intervals'=>[]];$this->assertSame('closed',$engine->check($schedule,'2026-11-07','23:00','01:30',1)['status']);
        $always=$this->schedule([7=>['state'=>'24h','intervals'=>[]]]);$this->assertSame('within_hours',$engine->check($always,'2026-11-08','00:00','00:00',1)['status']);
    }
    public function test_missing_unclear_and_missing_visit_date_are_not_reported_as_closed():void
    {
        $engine=new OpeningHours;$this->assertSame('unknown',$engine->check(null,'2026-11-08','09:00','10:00')['status']);
        $this->assertSame('unknown',$engine->check($this->schedule([1=>$this->open()]),'2026-11-08','09:00','10:00')['status']);
        $this->assertSame('date_required',$engine->check($this->schedule([1=>$this->open()]),null)['status']);
        $unknown=$this->schedule([]);$unknown['issue']='unclear_source';$this->assertSame('unknown',$engine->check($unknown,'2026-11-08')['status']);
    }
    public function test_structured_sheet_rows_support_multiple_shifts_and_fail_unclear_for_conflicting_rows():void
    {
        $headers=['place_id','rule_type','weekday','date','state','opens_at','closes_at','end_day_offset','timezone','data_quality'];
        $rows=[$headers,['DL001','weekly','1','','open','08:00','12:00','0','Asia/Ho_Chi_Minh','reference'],['DL001','weekly','1','','open','14:00','18:00','0','Asia/Ho_Chi_Minh','reference'],['DL001','exception','','2026-11-08','closed','','','','Asia/Ho_Chi_Minh','reference']];
        $mapped=(new OpeningScheduleMapper)->map($rows,['DL001']);$this->assertCount(2,$mapped['schedules']['DL001']['weekly'][1]['intervals']);$this->assertSame([],$mapped['issues']);
        $rows[]=['DL001','weekly','1','','closed','','','','Asia/Ho_Chi_Minh','reference'];$bad=(new OpeningScheduleMapper)->map($rows,['DL001']);
        $this->assertSame('unclear_source',$bad['schedules']['DL001']['issue']);$this->assertNotEmpty($bad['issues']);
    }

    public function test_current_display_uses_the_current_local_clock_independently_of_the_visit_date(): void
    {
        $engine=new OpeningHours;$rule=$this->open('08:00','22:00');$rule['source_url']='https://example.invalid/hours';$rule['checked_at']='2026-10-30';
        $schedule=$this->schedule([1=>$rule,7=>['state'=>'closed','intervals'=>[]]]);
        $visit=$engine->check($schedule,'2026-11-08','09:00','10:00');$this->assertSame('closed',$visit['status']);
        $display=$engine->display($schedule,null,new \Carbon\CarbonImmutable('2026-11-09T04:00:00Z'));
        $this->assertSame('open',$display['status']);$this->assertSame('Đang mở cửa · Đóng cửa vào 22:00',$display['summary']);
        $this->assertSame('Asia/Ho_Chi_Minh',$display['timezone']);$this->assertSame('2026-11-09',$display['local_date']);
        $this->assertCount(7,$display['weekly']);$this->assertSame('2026-10-30',$display['references'][0]['checked_at']);
        $this->assertSame('closed',$engine->display($schedule,null,new \Carbon\CarbonImmutable('2026-11-09T15:00:00Z'))['status']);
    }

    public function test_current_display_handles_split_shifts_overnight_and_date_exceptions(): void
    {
        $engine=new OpeningHours;$rule=$this->open('08:00','12:00');$rule['intervals'][]=['start'=>'14:00','end'=>'22:00','end_day_offset'=>0];$schedule=$this->schedule([1=>$rule]);
        $closed=$engine->display($schedule,null,new \Carbon\CarbonImmutable('2026-11-09 13:00','Asia/Ho_Chi_Minh'));
        $this->assertSame('closed',$closed['status']);$this->assertSame('Đang đóng cửa · Mở cửa vào 14:00',$closed['summary']);
        $night=$this->schedule([1=>$this->open('22:00','02:00',1)]);
        $this->assertSame('Đang mở cửa · Đóng cửa vào 02:00 ngày mai',$engine->display($night,null,new \Carbon\CarbonImmutable('2026-11-09 23:30','Asia/Ho_Chi_Minh'))['summary']);
        $this->assertSame('Đang mở cửa · Đóng cửa vào 02:00',$engine->display($night,null,new \Carbon\CarbonImmutable('2026-11-10 01:00','Asia/Ho_Chi_Minh'))['summary']);
        $night['exceptions']['2026-11-10']=['state'=>'closed','intervals'=>[]];
        $this->assertSame('closed',$engine->display($night,null,new \Carbon\CarbonImmutable('2026-11-10 01:00','Asia/Ho_Chi_Minh'))['status']);
        $schedule['exceptions']['2026-11-09']=$this->open('10:00','16:00');
        $this->assertSame('Đang mở cửa · Đóng cửa vào 16:00',$engine->display($schedule,null,new \Carbon\CarbonImmutable('2026-11-09 13:00','Asia/Ho_Chi_Minh'))['summary']);
    }

    public function test_current_display_does_not_infer_hours_from_free_text_unknown_days_or_missing_data(): void
    {
        $engine=new OpeningHours;$now=new \Carbon\CarbonImmutable('2026-11-09 11:00','Asia/Ho_Chi_Minh');
        $missing=$engine->display(null,null,$now);$this->assertFalse($missing['has_data']);$this->assertSame('Chưa có thông tin giờ hoạt động',$missing['summary']);
        $raw=$engine->display(null,'Tùy hôm; cần hỏi quán',$now);$this->assertSame('unknown',$raw['status']);$this->assertSame('Giờ hoạt động: Tùy hôm; cần hỏi quán',$raw['summary']);
        $unknown=$engine->display($this->schedule([2=>$this->open()]),null,$now);$this->assertTrue($unknown['has_data']);$this->assertSame('unknown',$unknown['status']);$this->assertStringStartsWith('Giờ hoạt động:',$unknown['summary']);
        $always=$engine->display($this->schedule([1=>['state'=>'24h','intervals'=>[]]]),null,$now);$this->assertSame('open',$always['status']);$this->assertStringContainsString('24 giờ',$always['summary']);
    }
}
