"""Report only committed DEMO metadata and dated real stay references, with explicit remaining price gaps."""
import collections,csv,json,pathlib
ROOT=pathlib.Path(__file__).resolve().parents[1];WORK=ROOT/'storage/app/private/room-catalog-update-20261031'
before=json.loads((ROOT/'storage/app/private/room-catalog-readiness-20261031/audit.json').read_text(encoding='utf8'))
after=json.loads((ROOT/'storage/app/private/room-catalog-readiness-20261031/audit-current.json').read_text(encoding='utf8'))
manifest=json.loads((ROOT/'database/data/demo-room-catalog-2026-10-31.json').read_text(encoding='utf8'))
rates=json.loads((ROOT/'database/data/verified-room-rate-references-2026-11-01.json').read_text(encoding='utf8'))['records']
with (ROOT/'docs/validation/room-type-enrichment-2026-10-31.csv').open(encoding='utf-8-sig',newline='') as f:
    original_catalog={int(r['hotel_id']):r for r in csv.DictReader(f)}
http_quotes=json.loads((WORK/'http-quote-report.json').read_text(encoding='utf8'))
applied=json.loads((WORK/'apply-report.json').read_text(encoding='utf8'));result=applied['result'];assert applied['checks']['all_passed']
types=[t for h in after['hotels'] for t in h['types'] if t['status']!='archived'];assert len(types)==362 and all(t['status']=='active' and t['capacity'] is not None and t['amenities'] for t in types)
assert applied['before']['room']==applied['after']['room'] and applied['after']['room']['count']==417
lines=['# Sức chứa, tiện ích, trạng thái và giá tham khảo — 01/11/2026','',
f"Đã ghi DB chính: bật **{result['types_activated']} loại** (tổng **362/362 loại chưa archived active**); điền **{result['capacities_filled']} sức chứa** gồm **{result['verified_capacities_filled']} có nguồn chính thức** và **{result['demo_capacities_filled']} cấu hình DEMO**. Giữ mọi sức chứa đã có. Bổ sung **{result['room_amenities_added']} mục tiện ích phòng**, **{result['hotel_amenities_added']} tiện ích gắn khách sạn**; giữ nguyên các mục cũ.",'',
'Giữ **40 khách sạn, 362 loại chưa archived, 30 loại archived và 417 phòng vật lý**; không tạo khách sạn/phòng/loại mới, không đổi giao diện hoặc logic đặt phòng. Các loại không có phòng vật lý vẫn không có tồn để đặt. 362/362 loại tiếp tục có ảnh.','',
'## Giá thật đã lấy được và cách dùng','',
'Có **7 báo giá trọn kỳ 15–17/11/2026 của Meliá Vinpearl Huế**, 2 người lớn, 1 phòng, VND, gồm thuế/phí. Nguồn Expedia đã hiển thị đúng ngày/khách và đúng từng hạng. Tổng giá gốc được lưu trong bằng chứng. Theo phạm vi DEMO, chia tổng 2 đêm cho 2 để lưu **giá bình quân tham khảo**, chỉ áp dụng đêm 15 và 16/11; tên bảng giá nói rõ kỳ và giá bình quân. Đây không phải bảng chi tiết giá từng đêm do Expedia trả về.','',
'**Không thêm giá cơ bản cố định mới, không nhân giá theo tên hạng và không chép giá thấp nhất của khách sạn sang mọi phòng.** Giá cơ bản và các giá ngày/mùa cũ được giữ. Các nguồn còn lại chưa có báo giá đầy đủ đúng hạng/ngày/điều kiện; những giá chưa xác minh tiếp tục để trống.','',
'| Hạng tại Meliá Vinpearl Huế | Tổng kỳ 2 đêm có thuế/phí | Bình quân DEMO mỗi đêm trong kỳ | Ăn sáng của gói nguồn |',
'|---|---:|---:|---|']
money=lambda s:f'{float(s):,.0f}'.replace(',','.')+' ₫'
for r in rates:lines.append(f"| {r['name']} | {money(r['stay_total_vnd'])} | {money(r['nightly_average_vnd'])} | {'Đã gồm' if r['breakfast_included'] else 'Chưa gồm; nguồn có lựa chọn trả thêm'} |")
lines+=['',f"[Nguồn báo giá đúng kỳ]({rates[0]['source_url']}). Kiểm tra nguồn 31/10/2026, ghi dữ liệu 01/11/2026. Điều kiện nguồn hiển thị hoàn tiền toàn bộ trước 12/11; chi tiết từng gói/ngày/khách và mã băm bằng chứng ở [manifest báo giá](../database/data/verified-room-rate-references-2026-11-01.json). Không nhập số phòng còn lại do OTA quảng bá thành tồn kho dự án.",'',
'## Kết quả từng khách sạn','',
'Số loại trước/sau tính từ trước toàn bộ đợt bổ sung danh mục đến hiện tại, chỉ đếm bản ghi chưa archived; có cả tên cũ được giữ nguyên để bảo toàn liên kết. Riêng đợt cấu hình 01/11 không thêm loại mới. Nguồn trong bảng là nguồn đối chiếu danh mục; tiện ích và sức chứa DEMO được ghi riêng, không giả làm thông tin thực tế đã xác nhận. Các giá cũ được giữ nhưng không tự coi là giá thị trường vừa xác minh.','',
'| Khách sạn | Số loại phòng trước/sau | Số phòng vật lý | Nguồn | Thông tin còn thiếu |',
'|---|---:|---:|---|---|']
rows=[]
for old_h in before['hotels']:
    h=next(h for h in after['hotels'] if h['id']==old_h['id']);selected=[r for r in manifest['records'] if r['hotel_id']==h['id']];quotes=[r for r in rates if r['hotel_id']==h['id']]
    old_types=[t for t in old_h['types'] if t['status']!='archived'];new_types=[t for t in h['types'] if t['status']!='archived'];rooms=sum(t['physical_rooms'] for t in h['types'])
    hotel_config=next(x for x in manifest['hotels'] if x['id']==h['id']);missing='Giá ngày ngoài kỳ đã xác minh; phân bổ tồn phòng' if quotes else 'Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng'
    original=original_catalog[h['id']]
    source=f"[Danh mục]({original['source']})"
    if quotes:source+=f"; [7 báo giá]({quotes[0]['source_url']})"
    row={'hotel_id':h['id'],'hotel':h['name'],'types_before_initial_enrichment':int(original['before']),'types_before_demo_update':len(old_types),'types_after':len(new_types),'capacities_filled':sum(r['before']['capacity'] is None for r in selected),'room_amenities_added':sum(len(r['amenities_added']) for r in selected),'hotel_amenities_added':len(hotel_config['amenities_added']),'dated_quote_types':len(quotes),'physical_rooms':rooms,'catalog_source':original['source'],'price_gap':missing,'unverified_real_metadata':original['missing']}
    rows.append(row);lines.append(f"| {h['name']} (ID {h['id']}) | {original['before']} → {len(new_types)} | {rooms} | {source} | {missing} |")
lines+=['','Chi tiết sức chứa/tiện ích thêm, giá và phần thông số thực tế chưa xác minh theo từng khách sạn: [CSV cập nhật](validation/room-catalog-update-2026-11-01.csv). Đối chiếu diện tích, giường, tên cũ và thông số còn thiếu nguồn: [báo cáo danh mục](room-type-enrichment-2026-10-31.md). Nguồn ảnh từng hạng: [báo cáo ảnh](room-type-photos-2026-10-31.md).']
lines+=['','## Nguồn, kiểm tra và giới hạn','',
'- [Cấu hình DEMO và các trường trước/sau](../database/data/demo-room-catalog-2026-10-31.json): sức chứa chỉ điền chỗ trống, tiện ích bổ sung theo giá dự án hoặc giá tham khảo xác minh được; không đổi dữ kiện cũ. 4 sức chứa mới lấy từ `HotelRoom.occupancy.maxValue` của [Cloudbeds chính thức MEANDER](https://hotels.cloudbeds.com/en/reservation/BscTin?currency=VND).',
'- Nguồn giá khác đã thử: Trip.com trả lỗi 430 ở API phòng; Booking.com không giữ lựa chọn ngày/báo giá; Agoda không trả trang báo giá; Cloudbeds lỗi ở dữ liệu giá; các link đặt trực tiếp đang trả 404. Expedia trả CAPTCHA khi tra nhiều cơ sở, nên dừng phiên tra đó và giữ các giá chưa có bằng chứng trống. Không giải CAPTCHA hoặc lách kiểm soát truy cập.',
'- Đã backup DB chính, khôi phục và đối chiếu 49 bảng/view trên MySQL cô lập, diễn tập toàn bộ thay đổi trước khi ghi. Hash chứng minh ảnh, phòng vật lý, booking/payment/snapshots và các bảng ngoài phạm vi không đổi. Giữ các giá/rate, sức chứa không trống, tiện ích khách sạn và bằng chứng cũ.',
'- Đọc khả dụng trước/sau cho 40 khách sạn khớp nhau. Giá kỳ 2 đêm qua cơ chế báo giá hiện có khớp tổng nguồn cho các hạng có phòng vật lý; không tạo tồn cho hạng mới chỉ để kiểm thử. Nhập lại tạo 0 thay đổi.',
'- HTTP đã kiểm tra 7 hạng có báo giá: 3 hạng có phòng vật lý trả 200, tổng kỳ khớp nguồn và ngày ngoài kỳ giữ giá cũ; 4 hạng không có phòng vật lý trả 404. Lần đọc lại đồng thời API của 40 khách sạn dừng do timeout, chưa xác nhận hoàn tất 40/40 ở đợt cập nhật này. Kết quả 40/40 trong báo cáo đợt ảnh là kết quả lịch sử. Không chạy thêm kiểm thử tự động theo yêu cầu dừng đã chốt.',
'- Dữ liệu thật chưa đủ: giá các hạng/ngày khác và phân bổ số phòng vật lý. Nhiều sức chứa/tiện ích mới là DEMO theo ủy quyền người dùng. Phân biệt này được lưu trong `hotel_room_type_evidence`, không sửa bằng chứng thực tế cũ.','']
lines+=['## Tự kiểm tra trên website','',
'1. Mở danh mục loại phòng ở Admin: các loại chưa lưu trữ đã active, có ảnh, sức chứa và tiện ích; các loại archived vẫn giữ nguyên.',
'2. Mở Meliá Vinpearl Huế, chọn 15–17/11/2026 và 2 người lớn: đối chiếu tổng 2 đêm của Deluxe Room, Premium Room và Suite với bảng trên. Chọn ngày ngoài kỳ để kiểm tra bảng giá tham khảo không bị kéo dài.',
'3. Thử các hạng không có phòng vật lý: không được tự xuất hiện tồn kho để đặt. Các booking cũ và chi tiết tiền đã lưu phải giữ nguyên.','']
if (ROOT/'storage/app/private/room-admin-update-20261102/final-summary-20261104.json').exists():
    lines[2:2]=['**Cập nhật tiếp nối 04/11/2026:** người dùng đã cho phép tạo phòng quản lý và dùng giá thật tham khảo làm giá cơ bản dự án. Hiện có 1.119 phòng vật lý, 232/362 loại có giá cơ bản; các số 417 phòng và 234 loại chưa có tồn dưới đây là lịch sử ngày 01/11. [Báo cáo hiện tại](room-admin-update-2026-11-04.md).','']
(ROOT/'docs/room-catalog-update-2026-11-01.md').write_text('\n'.join(lines),encoding='utf8')
with (ROOT/'docs/validation/room-catalog-update-2026-11-01.csv').open('w',encoding='utf-8-sig',newline='') as f:
    writer=csv.DictWriter(f,fieldnames=list(rows[0]));writer.writeheader();writer.writerows(rows)
summary={'applied_on':'2026-11-01','hotels':40,'active_types':len(types),'archived_preserved':sum(t['status']=='archived' for h in after['hotels'] for t in h['types']),'missing_capacity':sum(t['capacity'] is None for t in types),'empty_room_amenities':sum(not t['amenities'] for t in types),'missing_base_price':sum(t['base_price'] is None for t in types),'new_dated_quote_types':len(rates),'physical_rooms':417,'without_physical_rooms':sum(t['physical_rooms']==0 for t in types),'result':result,'checks':applied['checks'],'http_quote_records':http_quotes,'http_40_hotel_rerun':'incomplete_timeout','additional_automated_tests':'stopped_per_approved_requirements'}
(WORK/'final-summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),encoding='utf8');print(json.dumps(summary,ensure_ascii=False))
