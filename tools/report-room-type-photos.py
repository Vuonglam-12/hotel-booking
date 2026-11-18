"""Report applied photo changes, with a per-hotel summary and complete source inventory."""
import collections,csv,json,pathlib
ROOT=pathlib.Path(__file__).resolve().parents[1];WORK=ROOT/'storage/app/private/room-type-photo-completion-20261031'
before=json.loads((WORK/'audit-before.json').read_text(encoding='utf8'));after=json.loads((WORK/'audit-current.json').read_text(encoding='utf8'))
manifest=json.loads((ROOT/'database/data/verified-room-type-photos-2026-10-31.json').read_text(encoding='utf8'))
report=json.loads((WORK/'apply-report.json').read_text(encoding='utf8'));records=manifest['records']
http_photos=json.loads((WORK/'http-photo-report.json').read_text(encoding='utf8'))
http_rooms=json.loads((ROOT/'storage/app/private/room-type-enrichment-20261031/http-report.json').read_text(encoding='utf8'))
inventory=json.loads((ROOT/'storage/app/private/room-type-enrichment-20261031/before.json').read_text(encoding='utf8'))['hotels']
assert report['result']['images_added']==len(records)==125 and report['checks']['all_passed']
assert all(r['has_image'] for r in after) and len(before)==len(after)==362
assert len(http_photos)==125 and all(r['http_status']==200 and r['content_hash_matches'] for r in http_photos)
assert len(http_rooms)==40 and all(r['http_status']==200 and not r['new_unpriced_types_exposed'] for r in http_rooms)
hotels=collections.defaultdict(list)
for row in after:hotels[row['hotel_id']].append(row)
lines=['# Bổ sung ảnh loại phòng — 31/10/2026','',
'Đã ghi **125 ảnh và 125 bản ghi nguồn ảnh** vào database DEMO. Hiện **362/362 loại phòng chưa archived của 40 khách sạn** có ít nhất một ảnh tải được qua bộ xử lý ảnh hiện có (trước đợt ảnh: 237/362). Mỗi gallery trước đó trống nhận đúng một ảnh; giữ nguyên toàn bộ gallery cũ.','',
'Tại thời điểm hoàn tất đợt ảnh: danh mục **362 → 362**, phòng vật lý **417 → 417** (332 phòng chưa nghỉ quản lý). Đợt ảnh không tăng khách sạn, hạng phòng, tồn kho, giá hay trạng thái mở bán; lúc đó 234 hạng mới chưa có giá tiếp tục inactive. Trạng thái/sức chứa/tiện ích và giá tham khảo cập nhật sau đó được nêu ở [báo cáo 01/11/2026](room-catalog-update-2026-11-01.md). Không thay UI hoặc logic đặt phòng.','',
'Ảnh lấy từ gallery đúng hạng hoặc ảnh được nguồn đặt phòng gắn rõ với tên/ID hạng tại đúng cơ sở. Có ảnh phòng ngủ, phòng khách, phòng tắm, sân hiên/hồ bơi và ngoại thất villa trong chính gallery của hạng; không mô tả các ảnh này thành ảnh chụp từng số phòng vật lý. Các gallery cũ có nhiều ảnh tiếp tục được giữ.','',
'Ngày đối chiếu: **31/10/2026**. URL trang nguồn, URL ảnh, đường dẫn nội bộ và mã băm từng ảnh có trong [CSV nguồn ảnh](validation/room-type-photos-2026-10-31.csv) và [manifest](../database/data/verified-room-type-photos-2026-10-31.json); nguồn cũng đã ghi vào `hotel_room_type_evidence`.','',
'| Khách sạn | Loại có ảnh trước/sau | Phòng vật lý | Nguồn ảnh bổ sung (đại diện) | Còn thiếu ảnh |',
'|---|---:|---:|---|---:|']
for h in inventory:
    rows=hotels[h['id']];old=[r for r in before if r['hotel_id']==h['id']];add=[r for r in records if r['hotel_id']==h['id']]
    urls=[]
    for r in add:
        host=r['page_url'].split('/')[2]
        if not any(host==u.split('/')[2] for u in urls):urls.append(r['page_url'])
    source=', '.join(f'[Nguồn {i+1}]({url})' for i,url in enumerate(urls)) if urls else 'Giữ ảnh cũ'
    lines.append(f"| {h['name']} (ID {h['id']}) | {sum(r['has_image'] for r in old)} → {sum(r['has_image'] for r in rows)}/{len(rows)} | {h['physical_rooms']} | {source} | 0 |")
lines+=['','Không còn loại phòng chưa archived thiếu ảnh. Những dữ liệu chưa đủ bằng chứng ngoài ảnh (giá theo ngày/điều kiện, phân bổ tồn kho thực tế, một số thông số và đối chiếu tên cũ) vẫn để thiếu theo [báo cáo dữ liệu](room-type-enrichment-2026-10-31.md).','',
'Đã thử trên bản sao khôi phục từ backup trước khi ghi DB chính; so sánh toàn bộ bảng nghiệp vụ và dữ liệu cũ, xác nhận giá/tồn/trạng thái/booking cùng kết quả đọc của khách không đổi. Nhập lặp thêm 0 ảnh và 0 nguồn. **125/125 ảnh HTTP 200 và khớp mã băm; 40/40 API danh sách phòng HTTP 200**, không đưa các loại chưa có giá ra bán. Server MySQL diễn tập đã được tắt, dữ liệu thử và backup riêng tư được giữ để đối chiếu.','']
if (ROOT/'storage/app/private/room-admin-update-20261102/final-summary-20261104.json').exists():
    lines[2:2]=['Các gallery ảnh tiếp tục được giữ. Số phòng quản lý và giá đã được cập nhật trong đợt sau: [báo cáo 04/11/2026](room-admin-update-2026-11-04.md). Số phòng/trạng thái trong phần kiểm tra dưới đây là lịch sử đợt ảnh.','']
(ROOT/'docs/room-type-photos-2026-10-31.md').write_text('\n'.join(lines),encoding='utf8')
with (ROOT/'docs/validation/room-type-photos-2026-10-31.csv').open('w',encoding='utf-8-sig',newline='') as f:
    writer=csv.writer(f);writer.writerow(['hotel_id','hotel_room_type_id','room_type_id','hotel','room_type','checked_on','source_page_url','source_image_url','local_path','sha256','width','height','evidence_note'])
    for r in records:
        h=next(h for h in inventory if h['id']==r['hotel_id']);writer.writerow([r['hotel_id'],r['id'],r['room_type_id'],h['name'],r['name'],r['checked_on'],r['page_url'],r['source_url'],r['local_path'],r['sha256'],*r['dimensions'],r['evidence_note']])
summary={'hotels':len(hotels),'catalog_before':len(before),'catalog_after':len(after),'photos_added':125,'with_photo_before':sum(r['has_image'] for r in before),'with_photo_after':sum(r['has_image'] for r in after),'missing_photos':0,'physical_rooms':sum(h['physical_rooms'] for h in inventory),'source_rows_added':125,'checked_on':'2026-10-31','http_photos_verified':len(http_photos),'http_hotels_verified':len(http_rooms),'preservation_checks':report['checks']}
(WORK/'final-summary.json').write_text(json.dumps(summary,indent=2),encoding='utf8');print(json.dumps(summary))
