"""Publish non-sensitive import totals, hotel-by-hotel gaps, and evidence links."""
import collections
import csv
import json
import pathlib

ROOT=pathlib.Path(__file__).resolve().parents[1]
WORK=ROOT/'storage/app/private/room-type-enrichment-20261031'
BEFORE=json.loads((WORK/'before.json').read_text(encoding='utf-8'))
MANIFEST=json.loads((ROOT/'database/data/verified-room-types-2026-10-31.json').read_text(encoding='utf-8'))
APPLIED=json.loads((WORK/'apply-report.json').read_text(encoding='utf-8'))
REHEARSAL=json.loads((WORK/'rehearsal-report.json').read_text(encoding='utf-8'))
PHOTO_SUMMARY=ROOT/'storage/app/private/room-type-photo-completion-20261031/final-summary.json'
PHOTO_COMPLETE=PHOTO_SUMMARY.exists() and json.loads(PHOTO_SUMMARY.read_text(encoding='utf-8')).get('missing_photos')==0
CATALOG_SUMMARY=ROOT/'storage/app/private/room-catalog-update-20261031/final-summary.json'
CATALOG_UPDATED=CATALOG_SUMMARY.exists() and json.loads(CATALOG_SUMMARY.read_text(encoding='utf8')).get('active_types')==362
BATCHES=[APPLIED]
if (WORK/'apply-batch1-report.json').exists():
    batch1=json.loads((WORK/'apply-batch1-report.json').read_text(encoding='utf-8'))
    if batch1['manifest_sha256']!=APPLIED['manifest_sha256']:BATCHES.insert(0,batch1)

notes={
1:'Hai bản ghi phòng tập thể nam nữ cũ cùng tên được giữ nguyên ID; chỉ xác minh 1 hạng Mixed Dorm.',
2:'Rooms và Rates trên website có sức chứa khác nhau; không ghi đè số cũ. Hai tên Superior cũ cần đối chiếu/gộp thủ công có xem booking.',
3:'Chọn 10 hạng có nguồn; nguồn còn Connecting Room và Club Duplex Premier ngoài phạm vi mục tiêu.',
4:'Executive Suite cũ chưa khớp danh mục chính thức hiện tại; giữ nguyên.',
9:'5 hạng cũ Standard/Superior/Deluxe/Suite/Family chưa khớp tên chính thức; giữ nguyên. Bổ sung 10 hạng đã xác minh.',
11:'5 hạng cũ chưa khớp tên chính thức; giữ nguyên. Nguồn có nhãn giường/hướng nhìn bị hoán đổi và một số diện tích mâu thuẫn; các trường đó để trống.',
12:'2 tên Standard/Superior Suite cũ chưa có đối chiếu đổi tên sang danh mục mới; không gộp hoặc sửa ID.',
13:'5 hạng chung cũ chưa có đối chiếu chính xác; giữ nguyên. Nguồn xác minh 7 tên bổ sung gồm 2 villa ba phòng ngủ; không tách gói vui chơi/ăn uống.',
14:'Tên cũ “PHÒNG BA PHÒNG NGỦ...” không khớp 5 hạng trong website; giữ nguyên, cần kiểm tra thủ công.',
15:'4 hạng chung cũ ngoài Deluxe chưa khớp tên chính thức; giữ nguyên.',
16:'Hai bản ghi Ocean View One Bedroom Villa cũ giữ nguyên ID; không tạo thêm hạng này.',
18:'Giữ Family và tên cũ “4,137,116” chưa có nguồn đối chiếu. Không tự thay chúng thành hạng khác.',
19:'Chỉ thêm Two-Bedroom Chalet đã xác minh Booking.com. Deluxe Suite cũ chưa thể gán chắc với King Suite; chưa thêm các tên Penthouse của nguồn khác để tránh trùng.',
20:'Hai Superior Queen/Twin cũ cùng thuộc nhóm Superior trong nguồn; giữ ID riêng để bảo toàn liên kết.',
21:'Deluxe đã archived được giữ archived và không tạo bản ghi thay thế.',
43:'Hai Superior King/Twin cũ được giữ ID riêng. Một số dòng căn hộ khác có diện tích/mô tả không nhất quán, chưa nhập.',
44:'8 hạng King xác minh từ Booking.com. King Accessible và Twin Guest cũ chưa được crawl hiện tại xác minh lại, được giữ nguyên.',
45:'Nguồn đầy đủ có 8 hạng, gồm Executive Suite và 3 nhóm villa. Villa ba phòng ngủ hướng vườn có diện tích mô tả 349–393 m² nhưng badge 350 m²; giữ diện tích NULL.',
48:'Nguồn chỉ xác minh 7 hạng có tên cụ thể; mục “Villas” là nhóm nhiều căn, không thêm như một hạng thứ 8.',
50:'Đã kiểm tra cả trang 1 và trang 2 website cơ sở: 6 hạng; không bịa thêm 3–4 hạng.',
54:'Gộp các dòng chỉ khác số khách của cùng hạng. Không thêm Family/The Level khi chưa xác định được phòng vật lý khác với các hạng đã có.',
56:'Chỉ thêm 4 suite có bố trí riêng rõ ràng. Chưa thêm các biến thể Classic/Premium/Junior hướng nhìn/tầng cao vì tên cũ còn chung, chưa xác minh ánh xạ để tránh trùng.',
58:'Không tách Club chỉ theo quyền lounge.',
60:'1 Bedroom Suite cũ chưa khớp chắc với Residence; không thêm nhóm Residence đa bố trí như một loại mới.',
61:'Đã lấy đúng 10 hạng suite/villa trên IHG.',
}
source_override={19:'https://www.booking.com/hotel/vn/le-recit-boutique-de-dalat.fr.html',
44:'https://www.booking.com/hotel/vn/nhaan-resort-spa-hoi-an-tapestry-collection-by-hilton.html',
54:'https://www.expedia.com/Hue-Hotels-Melia-Vinpearl-Hue.h29267758.Hotel-Information',
56:'https://nhatrang.intercontinental.com/rooms/',58:'https://www.marriott.com/en-us/hotels/nhasi-sheraton-nha-trang-hotel-and-spa/rooms/premium-rooms/',
50:'https://samtuyenlamhotel.com.vn/category-stay/accommodation/?lang=en'}
rows=[]
for hotel in BEFORE['hotels']:
    h=hotel['id'];records=[r for r in MANIFEST['records'] if r['hotel_id']==h]
    result=APPLIED['first']['hotels'][str(h)]
    missing=[]
    if any(r['capacity'] is None for r in records):missing.append('sức chứa')
    if any(r['bed_type'] is None for r in records):missing.append('giường')
    if any(r['area_min_m2'] is None for r in records):missing.append('diện tích')
    if any(not r['amenities'] for r in records):missing.append('tiện ích riêng')
    if not PHOTO_COMPLETE and any(not r['images'] for r in records):missing.append('ảnh')
    aliases={i for r in records for i in r['existing_room_type_ids']}
    unmatched=[t['name'] for t in hotel['types'] if t['status']!='archived' and t['room_type_id'] not in aliases]
    if unmatched or h in [1,2,11,16,20,43,45,54,56]:missing.append('đối chiếu tên/dữ liệu')
    rows.append({'hotel_id':h,'hotel':hotel['name'],'before':len([t for t in hotel['types'] if t['status']!='archived']),'after':result['after'],
        'added':sum(b['first']['hotels'][str(h)]['created'] for b in BATCHES),'physical_rooms':hotel['physical_rooms'],'managed_rooms':hotel['managed_rooms'],
        'source':source_override.get(h,records[0]['catalog_source_url']),'checked_on':'2026-10-31',
        'missing':'Giá theo ngày; tồn phòng theo hạng; '+', '.join(missing),
        'retained_unmatched_names':'; '.join(unmatched),'note':notes.get(h,''),
        'reviewed_source_categories':len(records)})

out=ROOT/'docs/validation/room-type-enrichment-2026-10-31.csv'
out.parent.mkdir(parents=True,exist_ok=True)
with out.open('w',encoding='utf-8-sig',newline='') as f:
    writer=csv.DictWriter(f,fieldnames=list(rows[0]));writer.writeheader();writer.writerows(rows)
summary={'checked_on':'2026-10-31','hotels':40,'before':sum(r['before'] for r in rows),'after':sum(r['after'] for r in rows),
    'new_types':sum(b['first']['created'] for b in BATCHES),'enriched_existing':sum(b['first']['enriched'] for b in BATCHES),'images_attached':sum(b['first']['images_added'] for b in BATCHES),
    'evidence_rows':sum(b['first']['evidence_added'] for b in BATCHES),'physical_rooms':sum(r['physical_rooms'] for r in rows),
    'managed_rooms':sum(r['managed_rooms'] for r in rows),'rows':rows}
(WORK/'summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2),encoding='utf-8')

text=['# Bổ sung dữ liệu loại phòng — 31/10/2026','',
f"Đợt bổ sung danh mục ban đầu đã cập nhật database DEMO: **40 khách sạn, 128 → {summary['after']} bản ghi danh mục**, thêm **{summary['new_types']}** hạng, **{summary['enriched_existing']}** lượt bổ sung trường trống, gắn **{summary['images_attached']}** ảnh gốc và lưu **{summary['evidence_rows']}** bản ghi nguồn.",'',
'Số trước/sau đếm `hotel_room_type` chưa archived (bao gồm inactive và các tên cũ chưa khớp nguồn), không phải số hạng thực tế duy nhất đã xác minh. Các hạng cũ trùng/chung không bị xóa hoặc gộp ID. 324 hạng nguồn được chọn để đối chiếu; nhiều tên cũ có thể cùng ánh xạ về một hạng nguồn.', '',
f"**Phòng vật lý giữ nguyên {summary['physical_rooms']} bản ghi `room`, trong đó {summary['managed_rooms']} chưa `catalog_retired`.** Đây là số bản ghi dự án, không xác nhận tồn kho thực tế hay lượng còn bán cho một ngày lưu trú. Không tăng phòng, không ghi giá hay tạo khách sạn.",'',
f"Tại thời điểm hoàn tất đợt danh mục ban đầu, tất cả {summary['new_types']} loại mới: `inactive`, giá `NULL`, không có `room` và không có dòng `hotel_room_rate`. Giá/cấu hình DEMO cũ giữ nguyên. Lúc đó chưa tìm được báo giá có ngày lưu trú và đầy đủ điều kiện đủ chắc để đưa vào bảng giá; các mức “từ…”, gói ăn sáng/hủy và giá quảng bá đều không được nhập.", '',
'## Bảng kết quả thực tế','',
'Tất cả còn thiếu **giá theo ngày/điều kiện** và **dữ liệu phân bổ phòng vật lý theo hạng**. Cột dưới ghi thêm các phần còn thiếu ở ít nhất một hạng đã rà; không có nghĩa mọi hạng tại khách sạn đều thiếu trường đó. URL/ngày kiểm tra được lưu riêng từng hạng trong database và manifest.', '',
'| Khách sạn | Loại trước/sau | Phòng vật lý | Nguồn | Thiếu thêm |',
'|---|---:|---:|---|---|']
if PHOTO_COMPLETE:
    text[4:4]=['**Lượt bổ sung ảnh tiếp theo đã hoàn tất:** gắn thêm **125 ảnh**, hiện **362/362 loại phòng chưa archived** có ít nhất một ảnh. Lưu thêm 125 bản ghi nguồn ảnh (tổng 455 bản ghi bằng chứng). Không còn thiếu ảnh; các phần dữ liệu còn thiếu khác vẫn giữ nguyên. [Bảng ảnh và nguồn từng khách sạn](room-type-photos-2026-10-31.md).','']
if CATALOG_UPDATED:
    text[4:4]=['**Cập nhật 01/11/2026 theo yêu cầu mới:** 362 loại chưa archived đã active; điền 133 sức chứa còn trống (4 từ nguồn, 129 DEMO), bổ sung tiện ích và 7 giá bình quân tham khảo đúng kỳ 15–17/11 từ tổng giá thật. Các cột thiếu dưới đây mô tả độ đầy đủ nguồn thực tế của đợt ban đầu, không đồng nghĩa ô dữ liệu DEMO hiện tại còn trống. [Báo cáo hiện tại](room-catalog-update-2026-11-01.md).','']
if (ROOT/'storage/app/private/room-admin-update-20261102/final-summary-20261104.json').exists():
    text[2:2]=['**Trạng thái mới nhất 04/11/2026:** đã bổ sung 702 phòng quản lý và điền 104 giá cơ bản có nguồn theo quyết định tiếp nối của người dùng. Báo cáo dưới đây là lịch sử đợt danh mục ban đầu. [Xem số liệu hiện tại, nguồn giá và các mục còn thiếu](room-admin-update-2026-11-04.md).','']
for r in rows:
    extra=r['missing'].split('; ',2)[-1]
    text.append(f"| {r['hotel']} (ID {r['hotel_id']}) | {r['before']} → {r['after']} | {r['physical_rooms']} | [Nguồn]({r['source']}) | {extra or '—'} |")
text+=['','## Phần giữ lại hoặc chưa nhập','']
for h,note in notes.items():text.append(f"- **ID {h}:** {note}")
text+=['','Không tự chỉnh giá/sức chứa/giường đã có dù khác nguồn: xung đột được lưu trong `hotel_room_type_evidence.issues`. Danh mục bổ sung giữ khoảng diện tích bằng `area_min_m2`/`area_max_m2`, sức chứa kèm nguyên điều kiện người lớn/trẻ em trong bằng chứng. Một số ảnh chỉ thể hiện phòng tắm/ban công/sân hiên trong gallery đúng hạng; không dùng ảnh ngẫu nhiên hoặc ảnh sự kiện. Ảnh nguồn công khai chưa đồng nghĩa có giấy phép tái sử dụng; trạng thái quyền được ghi trong bằng chứng ảnh.','',
'## Kiểm tra và giới hạn','',
'- Hai đợt ghi đều có backup mới, đã phục hồi sang server MySQL cô lập và đối chiếu đủ 48 bảng/view ở đợt đầu, 49 bảng/view ở đợt bổ sung.',
'- Nhập trên bản phục hồi, chạy lại không đổi dữ liệu; kiểm tra từ chối sai chi nhánh, bản ghi lặp, giá/tồn giả và alias ngoài phạm vi. Ghi chính và chạy lại command đều đạt; lần chạy lại tạo 0 bản ghi.',
'- Hash toàn bộ các bảng nghiệp vụ ngoài phạm vi, mọi RoomType cũ, ảnh/link cũ và trường danh mục không trống giữ nguyên trong từng đợt ghi. Booking, booking_room, payment, bảng giá, hotel và room không bị importer sửa. Riêng bảng `personal_access_tokens` có trạng thái sử dụng do truy cập Admin và được tách khỏi điều kiện bằng nhau ở lần ghi bổ sung; importer không ghi bảng này.',
f"- Đọc API Customer qua controller cho đủ 40 khách sạn, có ngày 15–17/11/2026: danh sách phòng, sức chứa, khả dụng và báo giá cũ khớp trước/sau từng đợt nhập. Admin đọc được đủ {summary['after']} bản ghi danh mục. Báo giá cho loại mới không có phòng bị từ chối 404.",
'- `RoomPricingTest`: **5 tests / 45 assertions PASS** trên MySQL riêng.',
'- Bộ `HotelScopedRoomTypeTest` cũ: **14 tests / 103 assertions, 3 errors, 2 failures** do fixture thiếu bảng `booking_demo` và lỗi schema tiện ích cũ. Bộ này chưa áp dụng migration/dữ liệu đợt này; không sửa logic đặt phòng/giao diện để làm xanh bộ test. Kiểm tra hồi quy trên bản phục hồi có schema thực tế được nêu ở trên.',
'- HTTP thật trên localhost:8000: **40/40 API phòng HTTP 200**, không đưa loại mới chưa có giá ra bán. Chi tiết `storage/app/private/room-type-enrichment-20261031/http-report.json`.',
'- Sau đợt đầu có thay đổi phát sinh ngoài importer: `hotel_room_type` ID 157 (Regent, RoomType 64) đổi giường, có dòng `hotel_room_rate` ID 1 cho khách sạn 61 và bảng token thay đổi trạng thái sử dụng. Không đọc/in nội dung token. Đợt bổ sung lấy backup mới, giữ nguyên các thay đổi này; bằng chứng đối chiếu trong `post-import-drift.json`. Không phục hồi đè dữ liệu hoạt động mới để ép khớp snapshot cũ.','',
'## Tệp và chạy lại','',
'- Manifest dữ liệu không chứa credentials: [`database/data/verified-room-types-2026-10-31.json`](../database/data/verified-room-types-2026-10-31.json).',
'- Bảng đầy đủ/các tên cũ chưa khớp: [`validation/room-type-enrichment-2026-10-31.csv`](validation/room-type-enrichment-2026-10-31.csv).',
'- Schema dữ liệu bổ sung: migration `2026_10_31_000004_allow_verified_room_catalog_without_rates` chỉ cho phép thông tin chưa biết để NULL, thêm diện tích và bảng bằng chứng; không kích hoạt V2.',
'- Importer `tools/import-verified-room-types.php` yêu cầu backup đã phục hồi kiểm tra, manifest trùng với lần diễn tập và trạng thái DB đúng baseline. `--apply=<verified-backup.json>` tự trả kết quả không đổi nếu manifest và trạng thái đã khớp bản ghi chính; `--verify=<verified-backup.json>` chỉ xác minh.',
'- Bằng chứng HTML/hash, ảnh xem nhanh, backup và báo cáo trước/diễn tập/sau được lưu riêng trong `storage/app/private`; không chép credentials vào dữ liệu công khai hay Git. Không đụng Google Sheet.','']
(ROOT/'docs/room-type-enrichment-2026-10-31.md').write_text('\n'.join(text),encoding='utf-8')
print(json.dumps({k:v for k,v in summary.items() if k!='rows'},ensure_ascii=False))
