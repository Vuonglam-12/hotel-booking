<?php

namespace Tests\Unit;

use App\Services\Itinerary\TravelDialogue;
use PHPUnit\Framework\TestCase;

final class TravelReplyPresentationTest extends TestCase
{
    public function test_internal_references_and_branding_are_removed_without_losing_names_or_paragraphs():void
    {
        $text="AUREL gợi ý:\n\n- Bưu điện Trung tâm Sài Gòn (aurel:51)\n- Nhà thờ Đức Bà [AUREL:52]\n- Địa đạo Củ Chi (aruel:53)\n\nBạn muốn xem thêm từ AUREL không?";
        $result=(new TravelDialogue)->displayText($text);
        $this->assertStringStartsWith('Gợi ý:',$result);
        $this->assertStringNotContainsString('aurel',strtolower($result));$this->assertStringNotContainsString('aruel:',$result);
        foreach(['Bưu điện Trung tâm Sài Gòn','Nhà thờ Đức Bà','Địa đạo Củ Chi'] as $name)$this->assertStringContainsString($name,$result);
        $this->assertStringContainsString("\n\n",$result);$this->assertStringNotContainsString('()',$result);$this->assertStringNotContainsString('[]',$result);
    }

    public function test_normal_travel_information_is_preserved_and_reference_free_self_introduction_reads_naturally():void
    {
        $dialogue=new TravelDialogue;$text="Vé 180.000 ₫; ngày 2 đi nhẹ hơn.\n\nĐịa chỉ: số 51, đường A.";
        $this->assertSame($text,$dialogue->displayText($text));
        $this->assertSame('Xin chào, mình là trợ lý du lịch.',$dialogue->displayText('Xin chào, mình là AUREL.'));
        $this->assertSame('Tham quan điểm đã chọn.',$dialogue->displayText('Tham quan điểm đã chọn (geo:owned-place-0).'));
    }
}
