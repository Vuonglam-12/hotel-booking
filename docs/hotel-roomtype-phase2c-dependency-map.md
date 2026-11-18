# Phase 2C — dependency map trước chỉnh sửa

Đối chiếu 2A → B5 và source ngày 12/10/2026. Không kết nối database chính.

| Nhánh | Đường chạy V2 | Phụ thuộc / công việc 2C |
|---|---|---|
| Booking / stock | BookingServiceV2 → Inventory / Writer → BookingRoom snapshots | Giữ 15 phút, checkout exclusive, khóa Hotel → Types → Booking, không restore từ received_at |
| QR | manual acknowledgement → ManualBankingPaymentServiceV2 → audit/effect | Tách deadline thanh toán, cửa sổ xác minh, inventory; cảnh báo / queue đối soát |
| VNPay | Protocol → IPN → receipt / effect; Return read-only | Validation readiness và public ingress; synthetic contract không phải merchant E2E |
| Email | Effect → invoice/items + notification marker → sender sau commit | Lease, backoff, giới hạn retry, uncertain sau crash; audit manual review |
| Customer | Catalog → Home/Deals/map/detail → Type API; History/Payment/PDF snapshot | Checkout staging có gate, bỏ promotion random, giữ style AUREL |
| Admin | Owned Type CRUD/gallery/stock; BookingRead → verify/payment audit | Queue reconciliation + email review; giữ middleware / role |
| Wiring | Integration overlay → bridges + route priority + expiry alias + fence | Guard production riêng, prerequisite / target / cutover validation, provider chưa đăng ký |
| Offline data | Plan → Backfill → Remap → constraint → receipts → manual audit | Preflight source hiện tại, explicit allowlist, backup/restore proof, checkpoint / forward fix |

Production root controllers/services, routes, Auth, Kernel và UI gốc giữ nguyên. Legacy Webcontroller::paymentResult, PaymentController::createManual, Admin BookingService và Invoice PDF mutation phải bị thay route/binding hoặc fence khi cutover được phê duyệt. PDO trực tiếp, worker cũ và tiến trình ngoài app cần drain/restart và quyền DB; fence Laravel không bảo vệ tuyệt đối.

Nguồn tồn duy nhất sau activation là total_rooms + holding BookingRoom.quantity. Room/RoomImage/map/type legacy được giữ. 21/105/336 là fixture/kế hoạch cũ, không phải chứng cứ số lượng database chính hiện tại.

Thứ tự triển khai: policy → delivery schema/workflow → config → staging UI/HTTP/browser → deployment guard/wiring → acceptance/regression → tools/rehearsal → readiness/runbook. Config thiếu vẫn tiếp tục phần độc lập; không tự bật production.
