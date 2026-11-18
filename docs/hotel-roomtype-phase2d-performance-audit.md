# AUREL — Phase 2D performance audit

Delta so với Phase 2C, 12/10/2026. Chỉ tối ưu hai bottleneck đã đo. Không index/migration mới, không cache availability/price, không đổi transaction, thứ tự lock hoặc công thức tiền.

## Điều kiện và số đo

Owned MySQL 8.0.30, port **58603**, schema `aurel_roomtype_v2_test_c9600fb808e0`. Source fixture có 21 Hotel, **11 active**, 105 owned Type; History 20 booking cancelled, mỗi booking 1 pending payment. Clock synthetic cố định; warm-up + 20 lần service read trong PHP, cùng schema trước/sau, response SHA-256 khớp hoàn toàn. Đây không phải benchmark mạng, concurrency load hoặc SLA production.

| Probe | Query trước → sau | p50 trước → sau | p95 trước → sau | Mean SQL trước → sau |
|---|---:|---:|---:|---:|
| Catalog, page limit 21 trả 11 active Hotel | **116 → 11** | 34,4019 → **5,5352 ms** | 53,9715 → **7,1272 ms** | 29,5155 → 3,4355 ms |
| History, page 20 booking | **278 → 21** | 137,7674 → **14,9993 ms** | 166,4472 → **18,8653 ms** | 92,2825 → 7,6270 ms |

Artifact: `storage/app/private/roomtype-v2-tests/54c4b321bc5a/phase2d-performance-baseline.json` và `phase2d-performance-after.json`. p50/p95 dùng thứ tự mẫu thứ 10/19 của 20 mẫu đã sort; query count min=max trong từng probe.

Lượt baseline đầu có assertion fixture nhầm 21 active, đã sửa về 11 trước khi đo baseline hợp lệ. Lượt after đầu dùng fixture mới có timestamp account khác, chưa đủ điều kiện so sánh; thay bằng phép đo **reuse đúng schema baseline**, không đổi bản ghi và thêm assertions full response digest. Kết quả hợp lệ cuối ở `phase2d-tests-b68b0f42832b.txt`: **1 test /9 assertions PASS**. Log after cũ 1/3 không được dùng làm chứng cứ same-fixture.

## Thay đổi có bằng chứng

1. `HotelCatalogServiceV2::guard()` trước kiểm từng Type bằng query riêng sau danh sách Type. Nay lấy đủ trường một lần và gọi `RoomTypeV2Inventory::validateType()`. Hàm validation được tách nguyên điều kiện đang dùng; validation catalog và writer dùng chung. Kiểm unmapped/wrong-Hotel history, money, status, capacity và total_rooms giữ nguyên. Writer vẫn lock Hotel rồi từng Type theo ID tăng dần.
2. `BookingReadServiceV2` trước decorate từng booking/payment với lookup manual verification/receipt/effect riêng. Nay batch payment, manual verification, latest receipt và effects cho page; giữ lựa chọn successful payment trước, fallback mới nhất, outcome reconciliation, stored snapshots và response shape. Không đọc phòng vật lý để tính stock/giá.

EXPLAIN thu trong artifact là **lookup payment theo booking_id**, dùng index hiện có `idx_payment_booking`, access `ref`, estimate 1 row. Đây không phải EXPLAIN toàn catalog hoặc history; không dùng nó để kết luận mọi query tối ưu. Không thêm index production hoặc bỏ database identity/privilege checks để giảm query.

## Memory, frontend và phần chưa đủ dữ liệu

| Probe | Peak process memory baseline | After |
|---|---:|---:|
| Catalog | 35.651.584 bytes | 33.554.432 bytes |
| History | 39.845.888 bytes | 35.651.584 bytes |

Peak là cả tiến trình: baseline khởi tạo fixture, after reuse. Điều kiện bộ nhớ không giống nhau, **không kết luận memory được cải thiện**. Đo latency/query cùng fixture vẫn hợp lệ; không gọi đây là request memory.

Browser ghi resource entries 11 trang tại viewport 390px, chặn external fonts/CDN/maps. Local transferred bytes khoảng 1,0–1,25 MB đa số trang, Profile khoảng **3,64 MB** trong lượt audit. Đây là tải local có cache/HTML ảnh hưởng, không phải CWV. Home 8 ảnh, 6 lazy và có dimensions; Hotel Detail có 16 ảnh, 5 lazy, 9 không khai báo width/height; Profile còn 3 ảnh chưa có dimensions. Kích thước CSS container có thể vẫn giữ layout, chưa đo CLS để khẳng định có layout shift. Không tự transcode ảnh, thay CDN hoặc tạo số LCP/CLS giả.

- Catalog/filter và History đã có trước/sau; Admin dashboard aggregation, availability lịch dài, Invoice/PDF toàn request, transaction duration/lock contention ở tải production **chưa có benchmark đủ kết luận**. Tests concurrency B2–C PASS được giữ làm chứng cứ correctness, không coi là performance load test.
- Catalog/Type validation hiện còn quét tập Type; inventory sweep đọc lịch sử Type để fail closed. Chỉ đề xuất giới hạn read/cache advisory hoặc index khi đã có dataset/EXPLAIN/load được duyệt; confirmation luôn đọc stock/price dưới lock.
- Profile ảnh nền và bundle/font bytes cần đo tiếp ở release deployment khi được phép. Không thay build/dependency chỉ để tạo cải thiện trên môi trường đã chặn external resources.

## Regression đúng phần bị ảnh hưởng

B5 catalog price consumers, invalid/unmapped inventory, History/Admin/Payment/PDF: **4/46 PASS**, log `filtered-final-d8ddfe401d57-test-output.txt`. C funded-released reconciliation + hai production guard cases: **3/21 PASS**, log `filtered-acceptance-6845fdaa5e6d-test-output.txt`. Cả hai trong runtime `54c4b321bc5a`. Các bộ PASS không liên quan trong [matrix C](hotel-roomtype-phase2c-test-matrix.md) được giữ, không chạy lại toàn bộ.

**DONE:** hai N+1 và same-response regression trên synthetic. **PARTIAL:** load, CWV, memory riêng request và những query chưa đo. **NO-GO:** suy diễn số liệu này thành performance production đã nghiệm thu.
