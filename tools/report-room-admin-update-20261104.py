"""Current, non-sensitive report of committed stock, reference pricing, and Admin feedback."""
import collections,csv,json,pathlib
root=pathlib.Path(__file__).resolve().parents[1];work=root/'storage/app/private/room-admin-update-20261102'
before=json.loads((work/'audit-before.json').read_text(encoding='utf8'));after=json.loads((work/'audit-current.json').read_text(encoding='utf8'))
stock=json.loads((work/'inventory-apply-report.json').read_text(encoding='utf8'));prices=json.loads((work/'base-prices-apply-report.json').read_text(encoding='utf8'))
refs=json.loads((root/'database/data/reference-base-prices-2026-11-04.json').read_text(encoding='utf8'))['records'];by_type={r['hotel_room_type_id']:r for r in refs}
inventory=after['inventory'];types=after['types'];rows=[];details=[]
lines=['# Cập nhật giá, tổng phòng và thông báo Admin — 04/11/2026','',
f"Đã tạo **{stock['rooms_added']} phòng quản lý cho {stock['types_updated']} loại**, mỗi loại đang 0 phòng nhận 3 phòng. Hiện có **{inventory['physical']} phòng vật lý**, **{inventory['managed']} phòng đang quản lý**; 85 phòng nghỉ quản lý cũ được giữ. Danh mục vẫn 40 khách sạn, 362 loại chưa lưu trữ; 30 loại archived giữ nguyên. Đây là số lượng cấu hình của dự án theo yêu cầu người dùng.",'',
f"Đã điền **{prices['result']['base_prices_filled']} giá cơ bản còn trống**, thuộc **{len({r['hotel_id'] for r in refs})} khách sạn**, và cập nhật **{prices['result']['new_room_prices_filled']} phòng mới** tương ứng để nhận đặt. Hiện **{sum(r['base_price'] is not None for r in types)}/362 loại có giá cơ bản**; **{sum(r['base_price'] is None for r in types)} loại vẫn thiếu giá**. Các phòng mới chưa có giá giữ NULL và trạng thái chưa sẵn sàng, không có giá 0 để bán.",'',
'Giữ nguyên 128 giá cơ bản đã có, bảng phụ thu/giá ngày mùa, 417 phòng vật lý cũ, booking/payment/chi tiết tiền và ảnh/liên kết. Mọi loại chưa lưu trữ tiếp tục có ảnh theo gallery loại phòng, dùng chung cho các phòng vật lý tương ứng.','',
'## Giá đã cập nhật và phần còn thiếu','',
'Người dùng đã chọn dùng giá thật tham khảo làm giá cơ bản dự án. Phần lớn nguồn là báo giá Google Hotels theo từng hạng cho 15–17/11/2026, gồm tổng tiền toàn kỳ và điều kiện gói; một số hạng Regent dùng kỳ 07–11/12/2026 khi kỳ tháng 11 không có báo giá khớp. Bốn hạng Meliá Huế dùng tổng Expedia đã lưu ngày 31/10. Giá cơ bản được tính bằng tổng kỳ chia số đêm, lưu đến hai chữ số thập phân. Đây là lựa chọn giá quản lý của dự án; nguồn không xác nhận bán cùng mức giá cho mọi ngày.','',
'Không lấy giá thấp nhất của khách sạn gán cho mọi loại, không tách gói ăn sáng/hủy thành loại mới. Không dùng “Cheapest combo rooms”, giá giường tập thể chưa rõ đơn vị, hoặc tên/giường/hướng nhìn không đủ đối chiếu. Điều kiện và tên hạng nguyên văn được giữ trong bằng chứng.','',
'| Khách sạn | Loại phòng | Tổng phòng quản lý trước/sau | Giá cơ bản bổ sung / còn thiếu | Nguồn giá |',
'|---|---:|---:|---:|---|']
for hotel in dict.fromkeys(r['hotel_id'] for r in types):
    old=[r for r in before['types'] if r['hotel_id']==hotel];current=[r for r in types if r['hotel_id']==hotel];quotes=[r for r in refs if r['hotel_id']==hotel]
    unique=[]
    for quote in quotes:
        if quote['source_url'] not in unique:unique.append(quote['source_url'])
    source='; '.join(f'[Nguồn {i+1}]({url})' for i,url in enumerate(unique)) or 'Chưa có báo giá khớp hạng để bổ sung'
    row={'hotel_id':hotel,'hotel':current[0]['hotel'],'types':len(current),'managed_rooms_before':sum(r['total_rooms'] for r in old),'managed_rooms_after':sum(r['total_rooms'] for r in current),'base_prices_added':len(quotes),'missing_base_prices':sum(r['base_price'] is None for r in current),'source_urls':' | '.join(unique)}
    rows.append(row);lines.append(f"| {row['hotel']} (ID {hotel}) | {len(current)} | {row['managed_rooms_before']} → {row['managed_rooms_after']} | {len(quotes)} / {row['missing_base_prices']} | {source} |")
    for room in current:
        reference=by_type.get(room['id'])
        details.append({'hotel_id':hotel,'hotel':room['hotel'],'hotel_room_type_id':room['id'],'room_type_id':room['room_type_id'],'room_type':room['name'],'base_price_vnd':room['base_price'],'managed_rooms':room['total_rooms'],'price_state':'Vừa điền từ nguồn' if reference else 'Giữ giá đã có' if room['base_price'] is not None else 'Chưa có giá xác minh khớp hạng','source_url':reference['source_url'] if reference else '', 'checked_on':reference['checked_on'] if reference else '', 'reference_check_in':reference['check_in'] if reference else '', 'reference_check_out':reference['check_out'] if reference else '', 'source_room':reference['source_room_name'] if reference else '', 'source_conditions':reference['source_offer_text'] if reference else ''})
lines+=['','[Bảng chi tiết 362 loại: giá, tổng phòng, nguồn và mục còn thiếu](validation/room-admin-prices-inventory-2026-11-04.csv). [Manifest giá và bằng chứng](../database/data/reference-base-prices-2026-11-04.json). [Cấu hình tồn phòng](../database/data/room-inventory-configuration-2026-11-02.json).','',
'## Thông báo Admin','',
'- Hiện đồng thời từng lỗi: tên/giá/sức chứa/tổng phòng chưa nhập; giá sai định dạng hoặc âm; số lượng/sức chứa không nguyên, dưới hoặc vượt giới hạn; tầng/giường/mô tả/tiện nghi/trạng thái không hợp lệ.',
'- Có danh sách lỗi bấm để tới đúng ô, viền đỏ và hướng dẫn dưới từng ô. Lỗi của ô được cập nhật khi nhập lại; đặt con trỏ vào ô đầu tiên cần sửa. Áp dụng trang chi tiết khách sạn và modal loại phòng.',
'- Giá trống hiện “Giá phòng chưa nhập”; loại đang bật nhưng thiếu giá/tồn hiện “Đã bật · Chưa sẵn sàng”. Loại thuộc khách sạn nhưng chưa có phòng quản lý được báo đúng nguyên nhân. Lỗi máy chủ theo từng trường tiếp tục được hiển thị.',
'- Giữ form ảnh, trường tiện nghi, phụ thu ngày/mùa, ID, giới hạn và các chức năng hiện có.','',
'## Đã đối chiếu và tự kiểm tra','',
'Đã sao lưu và phục hồi đối chiếu 49 bảng/view trên MySQL cô lập trước mỗi đợt ghi. Khi ghi, đối chiếu hash các bảng ngoài phạm vi và mọi trường cũ; đợt giá chỉ sửa giá còn NULL và trạng thái của đúng phòng mới được cấp quyền, thêm bằng chứng. Giữ mã khóa nguồn để nhập lại không tạo thêm phòng/nguồn hay ghi đè thao tác quản trị về sau. Đã rà cú pháp PHP và biên dịch cú pháp JavaScript trong 4 view; không chạy bộ kiểm thử tự động hoặc thực hiện booking theo yêu cầu đã chốt. Chưa xác nhận tương tác trên trình duyệt ở đợt này.','',
'1. Tải lại trang Admin chi tiết khách sạn. Regent hiện 28 phòng quản lý; sáu hạng mới có giá, hai hạng villa ba phòng ngủ vẫn báo thiếu giá.',
'2. Mở một loại còn thiếu giá, giữ nguyên các ô khác rồi bấm lưu: chỉ báo “Giá phòng chưa nhập.” và trỏ tới ô giá.',
'3. Xóa thêm tên, sức chứa và tổng phòng: thấy từng lỗi cùng lúc; bấm một lỗi để tới đúng ô. Nhập số âm/số lẻ ở tổng phòng để xem lý do cụ thể, rồi nhập lại để lỗi cập nhật.',
'4. Kiểm tra cùng hành vi ở modal quản lý loại phòng; ảnh, tiện nghi và phụ thu vẫn còn. Khi thử lưu dữ liệu hợp lệ, dùng số lượng quản lý hiện có để tránh chủ động thay đổi tồn.',
'5. Các phòng mới chưa có giá vẫn chưa nhận đặt. Booking cũ giữ tiền và chi tiết từng đêm đã lưu.','',
'Phần giá chưa hoàn tất: 130 loại chưa có báo giá khớp đủ rõ hoặc nguồn không cung cấp giá phòng theo hạng. Một số nguồn trả yêu cầu xác minh người dùng; không vượt qua CAPTCHA, không tự chế giá để lấp đủ bảng.','']
(root/'docs/room-admin-update-2026-11-04.md').write_text('\n'.join(lines),encoding='utf8')
for file,data in [('room-admin-prices-inventory-2026-11-04.csv',details),('room-admin-hotels-2026-11-04.csv',rows)]:
    with (root/'docs/validation'/file).open('w',encoding='utf-8-sig',newline='') as f:
        writer=csv.DictWriter(f,fieldnames=list(data[0]));writer.writeheader();writer.writerows(data)
summary={'updated_on':'2026-11-04','hotels':len(rows),'types':len(types),'physical_rooms':inventory['physical'],'managed_rooms':inventory['managed'],'new_rooms':stock['rooms_added'],'new_base_prices':prices['result']['base_prices_filled'],'priced_types':sum(r['base_price'] is not None for r in types),'missing_base_prices':sum(r['base_price'] is None for r in types),'pending_rooms':inventory['pending_without_price'],'new_rooms_ready':prices['result']['new_rooms_ready'],'zero_inventory_types':sum(r['total_rooms']==0 for r in types),'price_preservation':prices['old_rooms_bookings_images_rates_and_other_fields_preserved']}
(work/'final-summary-20261104.json').write_text(json.dumps(summary,indent=2),encoding='utf8');print(json.dumps(summary))
