# Phân tích tính năng “Lịch trình AI” và nguyên nhân lịch Đà Nẵng rỗng

Ngày phân tích: **16/11/2026**, múi giờ người dùng Asia/Bangkok (UTC+7). Phạm vi: phân tích, không sửa code; file được tạo duy nhất là báo cáo này. Đã rà danh sách file và tìm tham chiếu trong repo, đọc luồng itinerary hiện hành, luồng legacy, chat, nguồn POI, schema, seed và các fixture/test liên quan. Không chạy test suite, migration, seed, sync Sheet, build hoặc API tạo/lưu lịch. Các số liệu runtime bên dưới được lấy bằng PDO trong transaction `READ ONLY` rồi `ROLLBACK`, cùng việc đọc trực tiếp cache file đã có; không bootstrap Laravel để tránh tác dụng phụ của cache/provider. Quy tắc giữ dữ liệu và không chạy thêm test được ghi tại `AGENTS.md:3-11`, `docs/itinerary-approved-requirements.md:15-17`, `docs/itinerary-approved-requirements.md:155`.

**Kết luận chính:** dữ liệu giờ tham khảo không đi tới bộ xếp slot. Snapshot Geoapify được đọc có 20 POI, không POI nào có `opening_hours`, `opening_schedule`, `regular_opening` hoặc `hours_text`; DB không có nguồn Sheet cho Đà Nẵng. Trong khi đó, planner loại mọi POI không có `opening_schedule` trước khi chọn ứng viên. Kết hợp với JSON bổ sung không chứa Đà Nẵng, tất cả POI còn lại sau các bước trước đó đều không qua được bộ lọc giờ. Sau đó backend tạo cảnh báo và vẫn lưu/trả HTTP 201 với lịch rỗng. Đây là kết luận từ code và snapshot hiện có, không phải kết quả gọi mới Geoapify/Groq. Bằng chứng: `app/Services/Itinerary/FixedSlotPlanner.php:19-24`, `app/Services/Itinerary/DailySlotPolicy.php:39-47`, `resources/content/itinerary/visit-facts.json:4-49`, `app/Http/Controllers/Api/V2/ItineraryPlannerController.php:84-87`, cùng R1–R4 dưới đây.

## Bằng chứng runtime chỉ đọc dùng trong báo cáo

Các R bên dưới là bản ghi kết quả quan sát của phiên phân tích, để phân biệt số liệu DB/cache với dữ liệu seed. Không suy ra đây chính là lịch của người báo lỗi vì không có itinerary ID từ trình duyệt; chỉ xác nhận có bản ghi khớp bộ điều kiện đã nêu. Cấu trúc bảng đối chiếu: `database/migrations/2026_10_23_000002_add_itinerary_trip_management.php:12-140`, `database/migrations/2026_10_31_000001_create_travel_source_places.php:9-19`, `database/migrations/2026_11_04_000001_add_itinerary_planning_metadata.php:10`.

<!-- evidence:R1 -->
**R1 — Location và dữ liệu Đà Nẵng.** Query `location JOIN itinerary_destination` trả `id=3, name='Đà Nẵng', code='vn-da-nang', enabled=1`. Query `attraction JOIN location WHERE location.name='Đà Nẵng'` trả 11 dòng, 11 active, 11 thiếu `experience_duration_minutes`, 11 thiếu `lat/lng`, 4 thiếu ít nhất một trong `open_time/close_time`, 0 thiếu `price_min/price_max`. Hai dòng có metadata xuất bản/xác minh/mã địa phương hợp lệ là ID 56 “Danh thắng Ngũ Hành Sơn” và 57 “Bán đảo Sơn Trà”, đều `experience_category='tham-quan'`, `item_type='attraction'`, `duration_hours=0`, `experience_duration_minutes=NULL`, `open_time=NULL`, `close_time=NULL`, `lat=NULL`, `lng=NULL`, `price_min=price_max=0`. Chín dòng còn lại không có slug/category/xác minh/xuất bản; không được tính là POI public đã duyệt.

<!-- evidence:R2 -->
**R2 — travel_source_place và cấu hình nguồn.** Query theo `location_id` cho Đà Nẵng trả **0 dòng**. Toàn bảng có Phú Quốc: 80 dòng, 50 enabled, 80 `opening_schedule=NULL`, 80 thiếu `hours_text`, 0 thiếu `visit_minutes`, 80 thiếu ít nhất một đầu giá, 80 thiếu tọa độ; Đà Lạt: 82 dòng, 50 enabled, 82 `opening_schedule=NULL`, 32 thiếu `hours_text`, 0 thiếu `visit_minutes`, 61 thiếu ít nhất một đầu giá, 82 thiếu tọa độ. Không đồng nhất “schedule NULL trong DB” với “không thể parse hours_text” ở Đà Lạt. Cờ cấu hình được kiểm tra chỉ ở mức boolean: Groq key có, Geoapify key có; primary Sheet location là Đà Lạt; không có cached Laravel config. Không ghi giá trị key/credentials vào báo cáo.

<!-- evidence:R3 -->
**R3 — cache Geoapify có sẵn.** Đọc trực tiếp `storage/framework/cache/data/6d/bb/6dbbe3d31e843437e86cee2f9bfa3894bf735816` cho region và `storage/framework/cache/data/a2/c5/a2c55f29b14745d5de429a489c08a563d03b1a18` cho places, không dùng `Cache::get/remember`. Region: `name='Thành phố Đà Nẵng', method='administrative_region'`, chưa hết hạn lúc đọc, hết hạn `2026-11-17T07:58:27Z`. Places: chưa hết hạn lúc đọc, hết hạn `2026-11-16T08:28:31Z`, 20 POI đã được GeoapifyClient chuẩn hóa/cache; 10 attraction, 10 restaurant; số có dữ liệu khác rỗng cho mỗi field `opening_hours/opening_schedule/regular_opening/hours_text` đều bằng 0. Đây là pool cache **trước** các bước `contains()`/merge public catalog trong TravelCatalog; không giả định cả 20 đều qua boundary hoặc closure filter. Cách tính key: `app/Services/Itinerary/GeoapifyClient.php:45-46`, `app/Services/Itinerary/GeoapifyClient.php:236`; định dạng file: `vendor/laravel/framework/src/Illuminate/Cache/FileStore.php:297-312`, `vendor/laravel/framework/src/Illuminate/Cache/FileStore.php:343-347`.

<!-- evidence:R4 -->
**R4 — bản ghi khớp tình huống.** Query aggregate `itinerary JOIN location` với `schema_version=2, location.name='Đà Nẵng', total_days=2, adults=2, stay_mode='own_accommodation', estimated_budget IS NULL` trả 1 lịch: `status='draft'`, 0 itinerary_item, `planning_metadata=NULL`, `transport=NULL`, `pace=NULL`, `activity_window_confirmed=0`. Bản replay `operation='planner:generate'` liên kết cùng bộ điều kiện có `response_status=201`, `days[0].items=[]`, `days[1].items=[]`, 11 `planning_warnings`. JSON response lưu có `planning_policy=null`, readiness `activities_generated=false`, `provider_candidates_available=false`, `generation_available=true`, `validation_status='not_checked'`. Budget lưu: `limit=null`, `scope='activities_only'`, `status='incomplete'`, `accounted_total=0`, `unknown_count=5`, gồm 2 `free_day_allowance`, 2 `selected_day_not_generated`, 1 `contingency_amount`; ngày neo là 17–18/11/2026.

<!-- evidence:R5 -->
**R5 — giá trị city/location và category quan sát.** `location` có: `Hà Nội/vn-ha-noi`, `Hồ Chí Minh/vn-ho-chi-minh`, `Đà Nẵng/vn-da-nang`, `Phú Quốc/NULL`, `Nha Trang/NULL`, `Hội An/NULL`, `Đà Lạt/vn-da-lat`, `Huế/NULL`; cả tám có destination enabled. `attraction.experience_category` distinct: `NULL` (15 attraction, 15 restaurant, 9 hotel, 8 activity), `tham-quan` (10 attraction). `travel_source_place.category` là nhãn tiếng Việt tự do, có cả nhãn đơn và nhãn ghép bằng en dash, xem danh sách đầy đủ ở R8. Category Geoapify trong cache R3: `leisure`, `leisure.park`, `heritage`, `tourism`, `tourism.sights`, `tourism.sights.ruines`, `tourism.sights.memorial`, `tourism.sights.memorial.monument`, `catering`, `catering.cafe`, `catering.restaurant`, `catering.restaurant.vietnamese`. INFORMATION_SCHEMA xác nhận ba bảng `location/attraction/travel_source_place` không có cột `city`.

<!-- evidence:R6 -->
**R6 — dữ liệu địa phương không nhất quán.** Trong 11 attraction gắn Đà Nẵng có ID 10–18 lần lượt “Dinh Độc Lập”, “Bảo tàng Chứng tích Chiến tranh”, “Chợ Bến Thành”, “Bánh Mì Huỳnh Hoa”, “Phở Lệ”, “Cơm tấm Ba Ghiền”, “Saigonciti Hotel”, “Liberty Central Saigon Centre”, “Ăn tối trên du thuyền sông Sài Gòn”. Cùng các tên đó được seed hiện tại xếp dưới `location_key='vn-ho-chi-minh'` (`database/seeders/AttractionSeeder.php:159-290`). Không tìm thấy bằng chứng lịch sử đủ để kết luận lệnh/import nào đã gắn sai các dòng hiện có. Query `itinerary_place_exclusion WHERE location_name='Đà Nẵng'` trả 0 dòng; rule JSON về Điện Hải vẫn tồn tại độc lập.

<!-- evidence:R7 -->
**R7 — nguyên văn warnings trong response replay đã lưu.** Có bốn câu cho ngày 1 và bốn câu cho ngày 2, lần lượt Sáng/Trưa/Chiều/Tối, theo mẫu: “Ngày 1 · Sáng: chưa đủ địa điểm đúng loại, giờ tham khảo, thời lượng hoặc ngân sách phù hợp; để trống khung này.”; hai câu pace theo mẫu: “Ngày 1: chỉ xếp được 0 điểm tham quan phù hợp, chưa đạt pace normal; không chèn điểm không phù hợp để đủ số lượng.”; câu thứ 11: “Chi phí ăn uống, lưu trú và di chuyển chưa có giá nguồn thì chưa nằm trong tổng tạm tính.”. Không có cảnh báo Khuya trong bản này. Nguồn trường: `itinerary_write_request.response_body.planning_warnings`, truy vấn chỉ đọc, không lấy request_key/customer/token/chat content.

<!-- evidence:R8 -->
**R8 — toàn bộ category distinct trong travel_source_place lúc đọc**, chép từ kết quả `SELECT DISTINCT category FROM travel_source_place` (không phải enum khai báo trong code):

```text
Vui chơi – thiên nhiên; Tham quan – thiên nhiên; Trải nghiệm – ngắm cảnh; Vui chơi – check-in
Tham quan – vườn hoa; Vui chơi – mua sắm; Vui chơi – nông trại; Tham quan – check-in
Tham quan – lịch sử; Tham quan – kiến trúc; Tham quan – di sản; Tham quan – văn hóa
Tham quan – tâm linh; Tham quan – cảnh quan; Check-in – công cộng; Ăn uống – mua sắm – dạo phố
Vui chơi – vườn thú; Vui chơi – văn hóa; Vui chơi – cà phê – check-in
Cà phê – check-in; Cà phê – trải nghiệm; Cà phê – ngắm cảnh; Cà phê – ăn nhẹ
Cà phê – âm nhạc; Cà phê – sân vườn; Cà phê – văn hóa; Cà phê – làm việc
Ăn uống – đặc sản; Ăn uống – bánh; Ăn uống – ăn sáng; Ăn uống – món chay
Ăn uống – nhà hàng; Ăn uống – món Ý; Ăn uống – brunch
Tham quan – hồ; Lịch sử – kiến trúc; Trải nghiệm – thiên nhiên; Tham quan – ngắm cảnh
Trải nghiệm – làng nghề; Thiên nhiên – thác; Văn hóa – tâm linh; Thể thao – golf
Trải nghiệm – cà phê; Kiến trúc – di sản; Kiến trúc – cảnh quan; Trải nghiệm – trà
Thiên nhiên – mùa hoa; Ăn uống – ăn vặt; Vui chơi; Bảo tàng; Biển; Thiên nhiên
Làng chài; Văn hóa; Tâm linh; Lịch sử; Tham quan; Trải nghiệm; Chợ – giải trí
Giải trí đêm; Chợ đêm; Ăn uống – hải sản; Ăn uống – cà phê; Chợ đêm – ăn uống
Làng nghề – nước mắm; Làng nghề – ngọc trai; Biển đảo – tham quan
Biển đảo – lặn ngắm san hô; Biển đảo – thiên nhiên; Biển – thư giãn
Trải nghiệm – chèo thuyền; Biển – thiên nhiên; Lịch sử – tâm linh
Tham quan – bến cảng; Tham quan – bến tàu; Thiên nhiên – mũi biển
Trải nghiệm – san hô; Làng nghề – đặc sản; Biển – cảnh quan
Ăn uống – giải trí; Nông nghiệp – tham quan
```

## 1. Cấu trúc tổng quan

### 1.1. Stack và điểm vào thật

| Thành phần | Stack và bằng chứng |
| --- | --- |
| Backend | PHP `^8.1`, Laravel `^10.10`, Sanctum `^3.3`, Guzzle `^7.2`, DomPDF `^3.1`; đây là constraints trong manifest, không gọi là phiên bản đang cài. `composer.json:7-13`. |
| Database | Eloquent/Query Builder, mặc định MySQL, charset utf8mb4; runtime đối chiếu bằng PDO MySQL. `config/database.php:18`, `config/database.php:46-63`; schema POI tại `database/migrations/2026_05_01_152156_create_attraction_table.php:14-34`. |
| Frontend | Blade và vanilla JavaScript thao tác DOM/fetch; JS itinerary được nạp trực tiếp từ `public/vendor/aurel-ai`, không phải component React/Vue. `resources/views/user/itineraries.blade.php:1-21`, `public/vendor/aurel-ai/planner.js:1-14`, `public/vendor/aurel-ai/chat.js:54-59`. |
| Build/style | Vite `^5`, Tailwind `^3.4.19`, laravel-vite-plugin; input Vite hiện là CSS. `package.json:4-14`, `vite.config.js:6-11`. |
| AI và bản đồ | Groq chat completions; model mặc định `openai/gpt-oss-20b`; Geoapify geocode/places/boundary; Leaflet cho bản đồ. `config/services.php:57-67`, `app/Services/Itinerary/GroqPlannerClient.php:18`, `app/Services/Itinerary/GeoapifyClient.php:20`, `app/Services/Itinerary/GeoapifyClient.php:53`, `resources/views/user/itineraries.blade.php:9-17`. |
| Nguồn Sheet | Service Account có scope `spreadsheets.readonly`, đồng bộ vào MySQL; scheduler 15 phút. `app/Services/Places/SheetsReader.php:10`, `app/Services/Places/SheetPlaceSync.php:78-80`, `app/Console/Kernel.php:16-17`. |
| Types/contracts | Không tìm thấy file TypeScript/interface DTO riêng cho itinerary. Contract thực tế là PHP array + FormRequest + JsonResource + JSON schema Groq + các hàm JS map dữ liệu. `app/Http/Requests/Itinerary/PlannerRequest.php:12-47`, `app/Http/Resources/ItineraryTripResource.php:29-89`, `app/Services/Itinerary/GroqItineraryContract.php:8-20`, `public/vendor/aurel-ai/planner.js:92-114`. |

Trang đang dùng là `GET /itineraries` → `user.itineraries`; route không trỏ vào file tên gần giống `resources/views/Itinerary.php`. File sau thực ra chứa một bản khai báo model cũ có field `city`, còn model hiện hành dùng `location_id`; không dùng file cũ làm bằng chứng rằng DB itinerary hiện có cột city. `routes/web.php:45`, `resources/views/user/itineraries.blade.php:13`, `resources/views/Itinerary.php:9-20`, `app/Models/Itinerary.php:17-26`.

### 1.2. Bản đồ module liên quan

| Nhóm | File và vai trò |
| --- | --- |
| UI hai bước | `resources/views/user/itineraries.blade.php:6-21`: asset/include; `resources/views/user/itineraries/planner-section.blade.php:11-19`: form; `resources/views/user/itineraries/planner-section.blade.php:23-38`: editor, Save/Undo/PDF, dialogs; `resources/views/user/itineraries/chat-section.blade.php:38-53`: chat composer. |
| JS và style | `public/vendor/aurel-ai/planner.js:14-16`: state; `public/vendor/aurel-ai/planner.js:253-309`: render; `public/vendor/aurel-ai/chat.js:295-328`: timeline; `public/vendor/aurel-ai/hours.js`, `public/vendor/aurel-ai/photos.js`: các renderer được trang nạp tại `resources/views/user/itineraries.blade.php:18-21`; hai stylesheet ở `resources/views/user/itineraries.blade.php:7-8`. |
| Routes/access | `routes/api.php:159`: auth Sanctum; `routes/api.php:215-231`: v2; `routes/api.php:204-212`: chat; `app/Http/Middleware/ItineraryV2Access.php:13-20`: Customer/schema gate. |
| Controller UI hiện tại | `app/Http/Controllers/Api/V2/ItineraryPlannerController.php:64-97`: write/generate/PDF; các endpoint photo/hours tại `app/Http/Controllers/Api/V2/ItineraryPlannerController.php:19-61`. |
| API v2 chi tiết | `app/Http/Controllers/Api/V2/ItineraryController.php:24-106`: destinations, CRUD, generate với TripRequest, status/delete. |
| Validation | `app/Http/Requests/Itinerary/PlannerRequest.php:12-78`, `app/Http/Requests/Itinerary/TripRequest.php:19-98`, `app/Http/Requests/Itinerary/MutationRequest.php:15-27`, `app/Http/Requests/Itinerary/StatusRequest.php:9-11`, `app/Http/Requests/Itinerary/DeleteRequest.php:7-9`; integer rule dùng tại `app/Http/Requests/Itinerary/PlannerRequest.php:15-20`. |
| Điều phối | `app/Services/Itinerary/PlannerService.php:16-85`: state/create/save; `app/Services/Itinerary/ConversationalPlanner.php:15-129`: chuẩn bị và ghép nhiều ngày; `app/Services/Itinerary/GenerationService.php:14-31`: gate và chuyển sang FixedSlotPlanner. |
| Thuật toán | `app/Services/Itinerary/FixedSlotPlanner.php:15-152`: chọn candidate, gọi AI, materialize; `app/Services/Itinerary/DailySlotPolicy.php:12-97`: slot/category/pace/giờ; `app/Services/Itinerary/PlanningPolicy.php:14-123`: dedupe, rank, duration, budget context. |
| AI contract/client | `app/Services/Itinerary/GroqItineraryContract.php:8-20`, `app/Services/Itinerary/GroqPlannerClient.php:12-35`. |
| POI và địa phương | `app/Services/Itinerary/DestinationService.php:11-43`, `app/Services/Itinerary/TravelCatalog.php:21-87`, `app/Services/Itinerary/GeoapifyClient.php:17-166`, `app/Services/Itinerary/PlaceAvailability.php:15-79`, `app/Services/Itinerary/VisitInformation.php:10-45`. |
| Google Sheets | `app/Services/Places/SheetPlaceSources.php:10-48`, `app/Services/Places/SheetsReader.php:13-82`, `app/Services/Places/SheetPlaceMapper.php:8-72`, `app/Services/Places/SheetPlaceSync.php:20-86`, `app/Services/Places/SheetPlaceCatalog.php:11-77`, `app/Services/Places/MapsUrl.php:6-13`, `app/Console/Commands/SyncPlaceSheet.php:9-19`. |
| Giờ hoạt động | `app/Services/Places/ReferenceHours.php:8-37`, `app/Services/Places/OpeningScheduleMapper.php:7-53`, `app/Services/Places/OpeningHours.php:12-145`, `app/Services/Places/ItineraryHours.php:13-71`. |
| Chat và state hội thoại | `app/Http/Controllers/ChatController.php:55-108`, `app/Services/Itinerary/TravelAssistantService.php:17-171`, `app/Services/Itinerary/TravelDialogue.php:12-94`, `app/Services/Itinerary/ConversationState.php:11-93`, `app/Services/Itinerary/TravelProgress.php:23-66`. |
| Lưu/giá/metadata | `app/Services/Itinerary/TripService.php:19-93`, `app/Services/Itinerary/GenerationService.php:34-73`, `app/Services/Itinerary/PlanningMetadata.php:12-42`, `app/Services/Itinerary/BudgetCalculator.php:11-148`, `app/Services/Itinerary/IdempotentWriter.php:10-41`, `app/Services/Itinerary/WriteGuard.php:10-19`. |
| Ảnh/copy/PDF | `app/Services/Itinerary/PlacePhoto.php:12-16`, `app/Services/Itinerary/PlannerPhotos.php:11-40`, `app/Services/Itinerary/ActivityCopy.php:18-22`, `resources/content/itinerary/place-photos.json`, `resources/views/pdf/itinerary.blade.php:4-26`; JSON ảnh được đọc tại `app/Services/Itinerary/PlacePhoto.php:66-70`. |
| Models | `app/Models/Itinerary.php:13-47`, `app/Models/ItineraryItem.php:10-29`, `app/Models/Location.php:9-19`, `app/Models/Attraction.php:9-22`; model `app/Models/Destination.php:9-25` ánh xạ bảng destination cũ, không phải travel_source_place hay Location chọn trên form; ChatMessage/ChatSession được ghi tại `app/Services/Itinerary/TravelAssistantService.php:161-167`. Các bảng snapshot/source/cost/calendar được truy cập bằng Query Builder, không có model POI chung bắt buộc. `app/Services/Itinerary/GenerationService.php:42-71`, `app/Services/Places/SheetPlaceCatalog.php:20-21`. |
| Schema | Bảng attraction gốc `database/migrations/2026_05_01_152156_create_attraction_table.php:14-34`; experience metadata `database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php:11-33`; bộ bảng v2 `database/migrations/2026_10_23_000002_add_itinerary_trip_management.php:12-140`; provisional `database/migrations/2026_10_29_000001_add_provisional_itinerary_flag.php:11-12`; source `database/migrations/2026_10_31_000001_create_travel_source_places.php:9-19`; giờ source `database/migrations/2026_10_31_000002_add_source_place_opening_schedule.php:7`; planning metadata `database/migrations/2026_11_04_000001_add_itinerary_planning_metadata.php:10`. |
| Seed và JSON | `database/seeders/DatabaseSeeder.php:19`: Location Đà Nẵng; `database/seeders/AttractionSeeder.php:22-727`: POI mẫu; `resources/content/itinerary/visit-facts.json:2-49`: metadata có thời hạn; `resources/content/itinerary/place-exclusions.json:4-10`: rule Điện Hải; `examples/google-sheets/Gio_hoat_dong.csv`: ví dụ tab, không phải data production; importer tab tại `app/Services/Places/OpeningScheduleMapper.php:7-17`. |
| Hệ thống trải nghiệm public | `app/Services/ExperienceCatalogService.php:18-29`, `app/Support/ExperienceSchema.php:9-20`, `app/Support/ExperienceCategory.php:7-11`, `app/Support/LocationIdentity.php:25-44`. |
| Legacy | `app/Http/Controllers/ItineraryController.php:20-52`: đọc và từ chối generate cũ; `app/Services/Itinerary/WriteGuard.php:10-13`: chặn ghi legacy; `routes/api.php:235-243`: route cũ còn giữ. |
| Test/docs tham chiếu | `tests/Feature/ItineraryPlannerTest.php:130-136`, `tests/Feature/ItineraryGeoapifyTest.php:62-73`, `tests/Feature/ItineraryPlanningPolicyTest.php:57-67`, `tests/Feature/ItineraryPlaceAvailabilityTest.php:35-77`, `tests/Feature/PlaceSheetIntegrationTest.php:61-214`, `tests/Frontend/itinerary-planner.browser.cjs:15`, `tests/Support/ItineraryFixture.php:34-64`; yêu cầu nền tại `docs/itinerary-approved-requirements.md:7-17`. Đây là tham chiếu tĩnh, không phải xác nhận test đang pass. |

## 2. Luồng dữ liệu end-to-end

### 2.1. Form → request

Form chỉ có điểm đến, số ngày, số người, ngân sách tùy chọn và stay mode. `formData()` lấy ID của option, ép các số bằng `Number()`, ngân sách trống thành `null`; không gửi chuỗi “Đà Nẵng” để backend query cột city. `resources/views/user/itineraries/planner-section.blade.php:12-16`, `public/vendor/aurel-ai/planner.js:92-95`.

Payload đúng tình huống, với `location_id=3` được đối chiếu bằng R1 chứ không mặc định cho mọi DB (`docs/itinerary-analysis.md:12`):

```json
{
  "location_id": 3,
  "duration_days": 2,
  "people": 2,
  "estimated_budget": null,
  "stay_mode": "own_accommodation"
}
```

Các field trên đi vào `POST /api/v2/itineraries/planner/generate`; fetch có Bearer token từ localStorage, Accept JSON, Content-Type JSON, `Idempotency-Key`; timeout client 300 giây. Không in/chia sẻ token khi debug. `public/vendor/aurel-ai/planner.js:69-75`, `public/vendor/aurel-ai/planner.js:363`, `routes/api.php:219`.

Trước đó, select tải `GET /api/v2/itineraries/destinations`; backend trả `{data:[{id,name,provider_region_verified}], place_restrictions:[...]}` từ Location có destination enabled, không yêu cầu POI đủ giờ mới hiện trong dropdown. `public/vendor/aurel-ai/planner.js:164-173`, `app/Http/Controllers/Api/V2/ItineraryController.php:24-29`, `app/Services/Itinerary/DestinationService.php:24-28`.

### 2.2. Backend

1. Sanctum + ItineraryV2Access kiểm tra Customer, loại admin token, kiểm tra schema; PlannerRequest kiểm tra field, request key, giới hạn 1–30 ngày và 1–8 người, cho phép budget NULL. `routes/api.php:159`, `app/Http/Middleware/ItineraryV2Access.php:15-20`, `app/Http/Requests/Itinerary/PlannerRequest.php:15-23`, `app/Http/Requests/Itinerary/MutationRequest.php:15-27`.
2. Controller kiểm tra cột provisional, tìm replay theo request key, lấy cache lock 300 giây. Nếu đã có response cùng key/payload, trả bản lưu, không tạo lại POI hay gọi AI. `app/Http/Controllers/Api/V2/ItineraryPlannerController.php:79-89`, `app/Services/Itinerary/IdempotentWriter.php:10-16`.
3. `PlannerService::state()` đổi `people→adults`, children/infants thành `[]`, `estimated_budget→budget`, `budget_flexible=true` khi NULL. Không có departure date trong form nên state không có start/end thật. `app/Services/Itinerary/PlannerService.php:16-22`.
4. ConversationalPlanner tạo ngày neo “ngày mai”, đủ hai ngày, provisional=true; mặc định drive và 08:00–23:59; đưa budget về `estimated_budget=null`, pace mặc định `moderate`. DailySlotPolicy gọi pace này là `normal`. `app/Services/Itinerary/ConversationalPlanner.php:19-40`, `app/Services/Itinerary/DailySlotPolicy.php:20-22`.
5. Lấy pool qua TravelCatalog. Đà Nẵng đi nhánh Geoapify + public catalog; sau đó GenerationService kiểm tra ngày/phương tiện/key/model rồi gọi FixedSlotPlanner. `app/Services/Itinerary/ConversationalPlanner.php:84-95`, `app/Services/Itinerary/TravelCatalog.php:44-87`, `app/Services/Itinerary/GenerationService.php:20-31`.
6. FixedSlotPlanner lọc, kiểm tra slot, có ứng viên mới gọi Groq; không ứng viên thì materialize với `$plan=null`, `activities=[]`, tạo warnings. Không có guard đòi ít nhất một activity. `app/Services/Itinerary/FixedSlotPlanner.php:21-24`, `app/Services/Itinerary/FixedSlotPlanner.php:58-59`, `app/Services/Itinerary/FixedSlotPlanner.php:90`, `app/Services/Itinerary/FixedSlotPlanner.php:116-152`.
7. Controller gọi writer trong DB transaction: tạo itinerary và calendar, persist activity thực có, tính resource và lưu response replay. Đường generate này lưu bản `draft` ngay trước khi người dùng bấm “Lưu lịch trình”. `app/Http/Controllers/Api/V2/ItineraryPlannerController.php:85-87`, `app/Services/Itinerary/IdempotentWriter.php:23-38`, `app/Services/Itinerary/PlannerService.php:51-64`, `app/Services/Itinerary/TripService.php:21-25`, `app/Services/Itinerary/GenerationService.php:47-73`.

### 2.3. Response → UI

Response HTTP 201 có `{data: ItineraryTripResource, assumptions: [...], planning_warnings: [...]}`. Resource chứa ID/revision/status, location, dates, party, budget, days/items, readiness và metadata. Với snapshot lỗi: hai `days[*].items` rỗng và 11 warnings top-level. `app/Http/Controllers/Api/V2/ItineraryPlannerController.php:87`, `app/Http/Resources/ItineraryTripResource.php:29-89`, `docs/itinerary-analysis.md:21`, `docs/itinerary-analysis.md:30`.

`fromResource()` map location/party/budget/day vào object `trip`. `start()` gán riêng `result.planning_warnings` vào `trip.generation_warnings`, đặt dirty=false, chuyển bước 2, hiện “Lịch AI đã sẵn sàng”. `render()` in warnings trong `tripPlanningNotes`; mỗi ngày hiện số activity và empty-state nếu items rỗng. Không có code tạo một fake activity cho từng warning Sáng/Trưa/Chiều/Tối. `public/vendor/aurel-ai/planner.js:109-114`, `public/vendor/aurel-ai/planner.js:257-277`, `public/vendor/aurel-ai/planner.js:363-371`.

```text
GET /itineraries [routes/web.php:45]
  → Blade form [planner-section.blade.php:11-19]
  → GET /api/v2/itineraries/destinations [planner.js:167]
  → formData() [planner.js:92-95]
  → POST /api/v2/itineraries/planner/generate [planner.js:363]
      → auth/access + PlannerRequest [routes/api.php:159,215-219]
      → replay / lock [ItineraryPlannerController.php:80-84]
      → PlannerService.state [PlannerService.php:16-22]
      → ConversationalPlanner.prepare [ConversationalPlanner.php:19-40,84-104]
          → TravelCatalog.pool [TravelCatalog.php:40-87]
              ├─ Sheet primary → travel_source_place
              └─ Đà Nẵng → Geoapify + attraction public → enrich giờ/giá
          → GenerationService.prepare [GenerationService.php:20-31]
          → FixedSlotPlanner [FixedSlotPlanner.php:19-90]
              → schedule filter → eligible → candidates
                  ├─ có candidates → Groq → validate → materialize
                  └─ không có     → plan=null → materialize → [] + warnings
      → transaction → itinerary/calendar/items/costs/replay [controller:85-87]
      → HTTP 201 {data, assumptions, planning_warnings}
  → fromResource + generation_warnings [planner.js:109-114,363]
  → bước 2: notes + days[*].items [planner.js:257-277]
```

Tên rút gọn trong sơ đồ là các file đầy đủ đã liệt kê ở §1.2; số dòng giữ theo file gốc.

### 2.4. Chat điều chỉnh là luồng khác

Chat gửi `message` và `planner_context` gồm field chuyến đi, visit date nếu có, pace/interests/reference, toàn bộ days/items; có version frontend để chặn proposal cũ. `public/vendor/aurel-ai/planner.js:103-107`, `public/vendor/aurel-ai/chat.js:408-428`.

`POST /api/chat/{session_id}/message` validate context, có thể stream SSE. TravelAssistantService ghép context mới, dùng Groq hiểu intent, merge state, gọi cùng ConversationalPlanner khi plan/revise_day. Trong editor, ngày thật được chuyển thành proposal date rồi bỏ start/end để không tự commit itinerary. Response có `assistant.components` chứa timeline; người dùng chọn Áp dụng/Bỏ qua. Chat message/context vẫn được ghi DB dù itinerary chưa được áp dụng. `app/Http/Controllers/ChatController.php:55-108`, `app/Services/Itinerary/TravelAssistantService.php:34-68`, `app/Services/Itinerary/TravelAssistantService.php:80-101`, `app/Services/Itinerary/TravelAssistantService.php:151-167`, `public/vendor/aurel-ai/planner.js:443-499`.

## 3. Thuật toán sinh lịch trình

### 3.1. Lấy và hợp nhất POI

- Sheet primary được xác định theo tên Location khớp cấu hình source. Lấy toàn bộ source đúng `location_id + source`, enabled, Maps hợp lệ; không chịu giới hạn 12 card của chat. Đà Nẵng không phải primary trong cấu hình/snapshot hiện tại. `app/Services/Places/SheetPlaceSources.php:35-42`, `app/Services/Places/SheetPlaceCatalog.php:11-26`, `config/services.php:71-86`, `docs/itinerary-analysis.md:15`.
- Ngoài Sheet: geocode region, lấy Polygon/MultiPolygon hoặc search bbox đã kiểm tra; request riêng nhóm tham quan/công viên và nhà hàng/cafe. Với chuyến 2 ngày `$extended=false`, provider limit là 20 tham quan + 10 ăn uống, không đảm bảo thực tế trả đủ 30. `app/Services/Itinerary/GeoapifyClient.php:20-35`, `app/Services/Itinerary/GeoapifyClient.php:45-55`, `app/Services/Itinerary/TravelCatalog.php:56-59`, `app/Services/Itinerary/ConversationalPlanner.php:84`.
- Public attraction query chỉ nhận active, chưa archived, slug, verified, published, location code khớp và category/type khớp. Tối đa 120 bản ghi; map không lấy giá legacy/open_time/close_time/duration_hours. `app/Services/ExperienceCatalogService.php:18-29`, `app/Services/Itinerary/TravelCatalog.php:24-35`.
- Merge theo tên chuẩn hóa: catalogue thiếu tọa độ chỉ mượn Geo tọa độ khi đúng một name match; vẫn phải nằm trong geometry. Khi đã dùng một Geo match, bản Geo đó bị bỏ khỏi nhánh thêm tiếp; không copy toàn bộ metadata giờ từ bản Geo sang bản public. `app/Services/Itinerary/TravelCatalog.php:60-73`.
- Gắn `opening_schedule` từ travel_source_place khi match được tên/địa chỉ trong cùng location/source; enrich VisitInformation; lọc closure lần nữa. `app/Services/Itinerary/TravelCatalog.php:77-86`, `app/Services/Places/ItineraryHours.php:13-25`.

### 3.2. Chuẩn bị candidates và scoring

1. Lọc closure, `_exclude_ids/excluded_ids` theo cả id/provider/source ID; dedupe theo provider_place_id, wikidata_id, cluster_id hoặc tên/aliases và khoảng cách. `app/Services/Itinerary/FixedSlotPlanner.php:18-20`, `app/Services/Itinerary/PlanningPolicy.php:14-34`.
2. `DailySlotPolicy::schedule()` ưu tiên opening_schedule → regular_opening → parse hours_text. Sau đó loại field schedule rỗng. Không có fallback lấy `opening_hours` hoặc open_time/close_time tại đây. `app/Services/Itinerary/FixedSlotPlanner.php:21-22`, `app/Services/Itinerary/DailySlotPolicy.php:39-47`.
3. Bỏ nơi đã dùng ở chunk trước; rank theo bộ khóa `[-interest_score, budget_pressure, original_index]`. Mỗi interest khớp substring vào tên/mô tả/categories chuẩn hóa được +1; không dùng rating/popularity/distance để scoring này. Budget pressure khi có budget: quote trong mức group/day→0, thiếu quote→1, quote vượt group/day→2; budget NULL→0 cho mọi POI. Đây là sắp thứ tự, không phải hard filter theo interest. `app/Services/Itinerary/FixedSlotPlanner.php:41-42`, `app/Services/Itinerary/PlanningPolicy.php:86-104`.
4. Tính `slot_availability[date]` bằng `start()` cho từng POI × ngày × 5 slot; không có slot hợp lệ thì loại. Với lịch provisional chưa chốt ngày, cần cùng khoảng giờ hợp lệ ở **cả bảy thứ** của tuần neo 02–08/11/2026, bỏ exceptions; không phải chỉ một thứ phù hợp là đủ. `app/Services/Itinerary/FixedSlotPlanner.php:43-48`, `app/Services/Itinerary/DailySlotPolicy.php:80-95`.
5. Chọn đại diện food tối đa `2×số ngày`, nightlife `1×số ngày`, visit `pace.min×số ngày`, rồi bổ sung theo rank tới `max(18, số ngày×(pace.min+3))`. Một POI có thể đồng thời thuộc visit và night; selected theo index nên không tạo hai candidate từ cùng index. `app/Services/Itinerary/FixedSlotPlanner.php:50-58`.
6. Chia ngày thành chunk 3 ở FixedSlotPlanner; lớp ConversationalPlanner lại bọc chunk 5 và truyền IDs/places đã dùng, chi phí đã tính giữa các phần. `app/Services/Itinerary/FixedSlotPlanner.php:26-31`, `app/Services/Itinerary/ConversationalPlanner.php:94-103`.

### 3.3. Slot và pace

| Slot | Khung | Loại được phép | Quy tắc |
| --- | --- | --- | --- |
| Sáng / morning | 08:00–11:30 | visit | Cộng vào số tham quan. |
| Trưa / lunch | 11:30–13:30 | food | Tối đa 1 POI. |
| Chiều / afternoon | 14:00–17:30 | visit | Cộng vào số tham quan. |
| Tối / dinner | 18:30–20:30 | food | Tối đa 1 POI. |
| Khuya / night | Từ 21:00 | nightEligible=true | Tối đa 1; không tính pace tham quan; bỏ slot/cảnh báo khi không có nightlife trong allPlaces. |

Nguồn tất cả dòng của bảng: `app/Services/Itinerary/DailySlotPolicy.php:12-17`, `app/Services/Itinerary/FixedSlotPlanner.php:100-111`, `app/Services/Itinerary/FixedSlotPlanner.php:141-145`.

`category()` nhận `food`, nhãn chứa `an uong/am thuc/nha hang/quan an`, hoặc item_type restaurant không phải cafe → food; còn lại → visit. `nightEligible()` dùng regex trên tên/mô tả/category/categories với các từ `dem/night/nightlife/bar/pub/nhac song/am nhac/pho di bo/buoi toi`. Đây là heuristic text, không xác minh cơ sở thực sự hoạt động về đêm. `app/Services/Itinerary/DailySlotPolicy.php:25-36`.

| Pace | Min | Max | Cách tính |
| --- | --- | --- | --- |
| relaxed | 2 | 2 | Tổng POI được chấp nhận ở morning+afternoon trong một ngày. |
| normal, moderate hoặc thiếu pace | 3 | 4 | `moderate` rơi vào default trả name `normal`. |
| packed | 5 | 8 | Bữa ăn và night không cộng vào chỉ tiêu. |

Nguồn: `app/Services/Itinerary/DailySlotPolicy.php:20-22`, `app/Services/Itinerary/FixedSlotPlanner.php:141`, `app/Services/Itinerary/TripService.php:73`.

**“Đạt pace normal” trong code materialize nghĩa là có ít nhất 3 visit được chấp nhận trong ngày**; upper bound 4 được kiểm tra ở output AI. Min không phải điều kiện để trả HTTP thành công: thiếu min chỉ thêm warning; validator `issues()` chỉ hard-check max. Vì vậy normal với 0 điểm vẫn đi hết luồng nếu không có lỗi khác. `app/Services/Itinerary/FixedSlotPlanner.php:103-111`, `app/Services/Itinerary/FixedSlotPlanner.php:149-152`.

### 3.4. AI và materialize

Groq chỉ nhận ID candidate, name, category, mô tả tối đa 200 ký tự, một phần categories, area, duration, availability, nightlife cùng party/budget/interests/requirements. Không nhận raw toàn bộ row POI; danh sách facts này cũng không có quote/giá từng candidate. Hai model GPT-OSS mặc định dùng strict JSON schema có khóa từng ngày; model khác dùng JSON object. `app/Services/Itinerary/FixedSlotPlanner.php:61-70`, `app/Services/Itinerary/GroqItineraryContract.php:8-20`.

Backend kiểm tra structure, đúng dates, đủ slot arrays, số lượng meal/night, candidate_id integer trong tập backend, không lặp, availability, reason và pace max. Thử sửa JSON tối đa một lần; HTTP 413 nhiều ngày được tách nhỏ; 429 có Retry-After phù hợp được chờ và retry tối đa một lần. Không có candidates thì hoàn toàn bỏ đoạn gọi Groq. `app/Services/Itinerary/FixedSlotPlanner.php:59-89`, `app/Services/Itinerary/FixedSlotPlanner.php:93-113`, `app/Services/Itinerary/GroqPlannerClient.php:20-30`.

Materialize duyệt slot theo thứ tự. Mỗi lựa chọn được tìm giờ sớm nhất hợp lệ, sau điểm trước ít nhất 30 phút, tính lại duration/quote, bỏ nếu vượt cap 110% ngân sách có nguồn, rồi mới thêm activity. Bỏ qua do không vừa slot hoặc budget không tự chọn POI khác để bù. Với planner hiện hành, travel là `planning_allowance`, time/distance NULL, `route_verified=false`; không gọi routematrix để xếp lịch. `app/Services/Itinerary/FixedSlotPlanner.php:121-140`, `app/Services/Itinerary/PlanningPolicy.php:11-12`; method matrix tồn tại tại `app/Services/Itinerary/GeoapifyClient.php:157-166` nhưng không được gọi trong luồng này.

Duration: nguồn suggested_duration_minutes được làm tròn bội 5, clamp 30–360 phút; thiếu nguồn thì food dùng 75, visit thường 90 với giới hạn theo loại; theme/water park tối thiểu 150, museum tối thiểu 75. Slot start thử từng 5 phút và yêu cầu toàn khoảng ghé nằm trong giờ tham khảo. Đây có giả định thời lượng, không đúng rằng mọi field duration thiếu đều tự động bị loại. `app/Services/Itinerary/DailySlotPolicy.php:50-54`, `app/Services/Itinerary/DailySlotPolicy.php:86-95`, `app/Services/Itinerary/PlanningPolicy.php:55-61`.

## 4. Nguồn dữ liệu POI và mismatch city/category

### 4.1. POI nằm ở đâu; Đà Nẵng có data không

| Nguồn | Dữ liệu dùng | Đà Nẵng |
| --- | --- | --- |
| MySQL attraction | Public/approved experience, description/categories/duration/image; không lấy giá và giờ legacy vào pool. `app/Services/Itinerary/TravelCatalog.php:24-35`. | Có 11 dòng, 2 dòng có metadata public; cả hai thiếu duration mới/giờ/tọa độ. Không kết luận “Đà Nẵng hoàn toàn không có POI”. `docs/itinerary-analysis.md:12`. |
| Geoapify / OpenStreetMap | HTTP geocode, boundary, places; POI cache 1.800 giây, region cache 86.400 giây. `app/Services/Itinerary/GeoapifyClient.php:19-20`, `app/Services/Itinerary/GeoapifyClient.php:46-55`. | Snapshot 20 POI với cả tham quan lẫn nhà hàng, toàn bộ thiếu giờ. `docs/itinerary-analysis.md:18`. |
| MySQL travel_source_place ← Sheet | Nguồn theo cấu hình, source key + external_id; fields nguồn/giờ/giá/duration/maps. `app/Services/Places/SheetPlaceCatalog.php:20-26`, `app/Services/Places/SheetPlaceCatalog.php:69-77`. | 0 dòng; không có fallback Sheet Đà Nẵng tự động. `docs/itinerary-analysis.md:15`, `app/Services/Places/SheetPlaceSources.php:35-42`. |
| visit-facts.json | Bổ sung metadata ticket và regular_opening theo location/name/coordinates, có hạn review. `app/Services/Itinerary/VisitInformation.php:12-23`. | Không có bản ghi Đà Nẵng: cả 5 records thuộc Hồ Chí Minh. `resources/content/itinerary/visit-facts.json:5-48`. |
| AttractionSeeder | Mẫu cho Hà Nội/HCM/Đà Lạt/Phú Quốc theo identity key; inactive, không tự publish. `app/Support/LocationIdentity.php:25-28`, `database/seeders/AttractionSeeder.php:731-744`. | Không tìm thấy bộ mẫu Đà Nẵng trong seeder này. Dòng seed Location Đà Nẵng không đồng nghĩa có seed POI Đà Nẵng. `database/seeders/DatabaseSeeder.php:19`. |

Không tìm thấy OpenAI API, Google Places API hoặc vector database được gọi để tạo lịch trong các module hiện hành; các provider call cụ thể là Geoapify và Groq ở `app/Services/Itinerary/GeoapifyClient.php:190-207`, `app/Services/Itinerary/GroqPlannerClient.php:14-18`. Tên model có tiền tố `openai/` trong cấu hình Groq không đổi endpoint thành OpenAI API. `config/services.php:59`, `app/Services/Itinerary/GroqPlannerClient.php:18`.

### 4.2. Field bắt buộc và field có thể thiếu

| Field logic | Schema/source | Cách planner dùng và tình trạng thiếu |
| --- | --- | --- |
| city/location | Attraction/source có location_id; Location có name/code. Không có city column ở ba bảng này. `app/Models/Location.php:13-19`, `database/migrations/2026_05_01_152156_create_attraction_table.php:16-20`, `database/migrations/2026_10_31_000001_create_travel_source_places.php:10-12`. | Form gửi integer location_id; Geo xác minh name/city chuẩn hóa; không query `WHERE city='Đà Nẵng'`. `public/vendor/aurel-ai/planner.js:93`, `app/Services/Itinerary/GeoapifyClient.php:24-26`, `docs/itinerary-analysis.md:24`. |
| category | attraction.item_type enum NOT NULL; experience_category mới nullable; Sheet category NOT NULL. `database/migrations/2026_05_01_152156_create_attraction_table.php:22`, `database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php:16`, `database/migrations/2026_10_31_000001_create_travel_source_places.php:12`. | Runtime dùng cả category/categories/item_type, qua policy. Hai public Đà Nẵng có category tham-quan; 9 legacy thiếu category mới. `app/Services/Itinerary/DailySlotPolicy.php:25-30`, `docs/itinerary-analysis.md:12`. |
| duration | duration_hours legacy NOT NULL default 2; experience_duration_minutes nullable; source.visit_minutes nullable. `database/migrations/2026_05_01_152156_create_attraction_table.php:28`, `database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php:18`, `database/migrations/2026_10_31_000001_create_travel_source_places.php:14`. | Pool public chỉ lấy experience_duration_minutes; policy fallback. 11/11 Đà Nẵng thiếu duration mới; 2 public còn duration_hours=0 nhưng field này không được dùng. `app/Services/Itinerary/TravelCatalog.php:33`, `app/Services/Itinerary/DailySlotPolicy.php:50-54`, `docs/itinerary-analysis.md:12`. |
| price | Giá legacy NOT NULL default 0; source price_min/max nullable; quote yêu cầu ticket metadata riêng. `database/migrations/2026_05_01_152156_create_attraction_table.php:26-27`, `database/migrations/2026_10_31_000001_create_travel_source_places.php:13`, `app/Services/Itinerary/VisitInformation.php:28-45`. | Có số legacy/price_reference không đồng nghĩa quote có nguồn dùng được. Hai public Đà Nẵng giá legacy 0 không được công nhận miễn phí. `app/Services/Itinerary/TravelCatalog.php:32`, `docs/itinerary-analysis.md:12`. |
| opening hours | Legacy open_time/close_time nullable; source.hours_text và opening_schedule nullable. `database/migrations/2026_05_01_152156_create_attraction_table.php:29-30`, `database/migrations/2026_10_31_000001_create_travel_source_places.php:13`, `database/migrations/2026_10_31_000002_add_source_place_opening_schedule.php:7`. | Runtime bắt buộc chuyển được thành schedule + check within_hours. Nguồn Đà Nẵng không cung cấp được schedule ở cả ba đường enrich đang có. `app/Services/Itinerary/DailySlotPolicy.php:39-47`, `app/Services/Itinerary/FixedSlotPlanner.php:22`, `docs/itinerary-analysis.md:12`, `docs/itinerary-analysis.md:15`, `docs/itinerary-analysis.md:18`. |
| coordinates | DB lat/lng nullable; Geo đòi coordinates hợp lệ; Sheet được phép thiếu tọa độ. `database/migrations/2026_05_01_152156_create_attraction_table.php:31-32`, `app/Services/Itinerary/GeoapifyClient.php:60-61`, `app/Services/Places/SheetPlaceCatalog.php:75`. | Public Đà Nẵng cần match tên Geo để mượn tọa độ; Sheet thiếu tọa độ vẫn có thể xếp với allowance. `app/Services/Itinerary/TravelCatalog.php:64-66`, `app/Services/Itinerary/FixedSlotPlanner.php:138-139`, `docs/itinerary-analysis.md:12`. |

### 4.3. Format location/category cần so sánh đúng tầng

| Tầng | Format / ví dụ | Rủi ro mismatch và bằng chứng |
| --- | --- | --- |
| UI request | `location_id:3`, không phải `city:'Đà Nẵng'`. | ID lấy từ API, không hard-code cho DB khác. `public/vendor/aurel-ai/planner.js:167-169`, `public/vendor/aurel-ai/planner.js:93`. |
| Location | `name='Đà Nẵng'`, `code='vn-da-nang'`; đầy đủ tám tên/mã ở R5. | Public query đòi location.code=experience_location_code; code NULL loại local public POI nhưng không tự loại toàn bộ Geo POI. `app/Services/ExperienceCatalogService.php:23-24`, `docs/itinerary-analysis.md:24`. |
| Region Geo | `name='Thành phố Đà Nẵng'`, country_code `vn`, result_type `city`. | Geo normalizer bỏ tiền tố `thanh pho/tinh`, transliterate; hai tên này khớp. Đây không phải bằng chứng mismatch trong snapshot. `app/Services/Itinerary/GeoapifyClient.php:24-26`, `app/Services/Itinerary/GeoapifyClient.php:247-249`, `docs/itinerary-analysis.md:18`. |
| Sheet location | So khớp Location.name với `location_name` cấu hình bằng ascii/lower/trim/space normalize. | Không dùng cùng normalizer bỏ tiền tố của Geo; nhãn `Thành phố Đà Nẵng` và `Đà Nẵng` trong cấu hình Sheet có thể không khớp. Chưa có source này hiện tại. `app/Services/Places/SheetPlaceSources.php:37-41`, `app/Services/Places/SheetPlaceMapper.php:17`, `docs/itinerary-analysis.md:15`. |
| Public categories | Keys `tham-quan`, `am-thuc`, `vui-choi`, `dia-phuong`; type tương ứng attraction/restaurant/activity/activity. | Không so key slug trực tiếp với label tiếng Việt; runtime hiện chỉ có tham-quan và NULL. `app/Support/ExperienceCategory.php:7-11`, `docs/itinerary-analysis.md:24`. |
| Geo categories | Mảng dotted strings, ví dụ tourism.sights, leisure.park, catering.restaurant, catering.cafe. | Category đơn không được lấy từ provider; item_type được suy từ categories; cafe có thể thành visit theo policy. `app/Services/Itinerary/GeoapifyClient.php:67-82`, `app/Services/Itinerary/DailySlotPolicy.php:27-30`, `docs/itinerary-analysis.md:24`. |
| Sheet categories | Unicode label đơn/ghép: “Ăn uống – nhà hàng”, “Cà phê – check-in”, “Giải trí đêm”, “Biển”; đầy đủ R8. | SheetPlaceCatalog map bằng substring `an uong/ca phe`; DailySlotPolicy map lại food/visit/night text. Không có enum category chung xuyên provider. `app/Services/Places/SheetPlaceCatalog.php:65-77`, `app/Services/Itinerary/DailySlotPolicy.php:25-36`, `docs/itinerary-analysis.md:33`. |
| Planning categories | `visit`, `food`, `nightlife`; slot `morning/lunch/afternoon/dinner/night`. | Đây là taxonomy nội bộ, không phải giá trị phải có sẵn trong attraction.experience_category. `app/Services/Itinerary/DailySlotPolicy.php:12-17`, `app/Services/Itinerary/FixedSlotPlanner.php:134-136`. |

## 5. Các điều kiện filter và validation

Ký hiệu **HẾT POI**: điều kiện có thể làm tập ứng viên còn 0; không có nghĩa đã xác nhận nó xảy ra trong request cụ thể. **LỖI**: request dừng bằng error thay vì lịch rỗng. **MỀM**: chỉ rank/cảnh báo. Mỗi đoạn inline là biểu thức code ở vị trí dẫn, không phải bản sửa đề xuất.

| Bước | Điều kiện/code cốt lõi | Hệ quả và mức liên quan |
| --- | --- | --- |
| Destination | `DestinationService::supported($location_id)`; Location tồn tại và itinerary_destination.enabled=true. `app/Services/Itinerary/DestinationService.php:11-14`. | **LỖI 422** khi không hỗ trợ; R1 cho thấy Đà Nẵng được bật. |
| Form | `estimated_budget => present, nullable`; days 1–30, people 1–8, stay enum; day_trip phải 1 ngày. `app/Http/Requests/Itinerary/PlannerRequest.php:18-23`, `app/Http/Requests/Itinerary/PlannerRequest.php:59-64`. | **LỖI 422**; thiếu budget không phải hard filter POI. |
| Generation gate | Ngày quá khứ; chưa confirm window/thiếu start-end; transport không thuộc walk/drive/bicycle; thiếu Groq key/model. `app/Services/Itinerary/GenerationService.php:20-28`. | **LỖI 422/503**, không tạo đúng chuỗi warnings slot trống này. |
| Region | country vn, result_type city, category administrative/populated_place, name normalized khớp, ID/tọa độ hợp lệ, duy nhất một region. `app/Services/Itinerary/GeoapifyClient.php:24-34`. | **LỖI** region_unverified; search bbox còn bị giới hạn 80 km mỗi chiều tại `app/Services/Itinerary/GeoapifyClient.php:238-244`. |
| Provider fields | `country_code!='vn'`, ID thiếu/dài >255, name trống, coordinates sai → `continue`. `app/Services/Itinerary/GeoapifyClient.php:60-61`. | **HẾT POI**; general còn dưới 2 thì **LỖI** trước merge tại `app/Services/Itinerary/GeoapifyClient.php:93`. |
| Provider dedupe/focus | `$names[$nameKey]` bỏ trùng tên; focus entertainment/food phải categories phù hợp. `app/Services/Itinerary/GeoapifyClient.php:62-70`. | **HẾT POI** về lý thuyết; form generate dùng general, không filter theo sở thích food-only. |
| Geometry | `contains($geometry,$place['coordinates'])`; public thiếu tọa độ/match tên không duy nhất hoặc ngoài boundary → continue. `app/Services/Itinerary/TravelCatalog.php:59`, `app/Services/Itinerary/TravelCatalog.php:64-66`. | **HẾT POI**; nếu rỗng ngay bước gộp thì lỗi insufficient_places tại `app/Services/Itinerary/TravelCatalog.php:75`. |
| Public publication | `is_active`, archived NULL, slug/verified có, published<=now, code khớp, category/type khớp. `app/Services/ExperienceCatalogService.php:21-28`. | **HẾT local POI**; không loại Geo chưa merge. 9 dòng legacy Đà Nẵng không đạt publication. `docs/itinerary-analysis.md:12`. |
| Source enable | `$source && !$source->is_enabled → continue`; đúng source/location. `app/Services/Itinerary/TravelCatalog.php:79-81`, `app/Services/Places/ItineraryHours.php:15-25`. | **HẾT POI** sau check pool-not-empty; có thể rơi vào lịch rỗng vì không check lại ở cuối pool. |
| Sheet primary query | `where(location_id)->where(source)->where(is_enabled,true)` và Maps URL hợp lệ. `app/Services/Places/SheetPlaceCatalog.php:20-26`. | **HẾT POI**; primary general rỗng trả sheet_places_unavailable, không fallback Geo. `app/Services/Itinerary/TravelCatalog.php:45-54`. |
| Sheet import validity | Thiếu ID/name/category/area, Maps sai, giá đảo min/max, duration>1440, tọa độ sai/cặp thiếu, ngày sai → skip; status không thuộc allowlist→disabled. `app/Services/Places/SheetPlaceMapper.php:38-53`. | **HẾT POI enabled**; chưa liên quan trực tiếp Đà Nẵng vốn không có source. |
| Closure | `permanentlyClosed()` hoặc rule JSON/DB đúng location + ID/name/address/≤150m → blocked. `app/Services/Itinerary/PlaceAvailability.php:15-59`. | **HẾT POI** nếu tất cả bị chặn; rule JSON hiện chỉ nhắm Điện Hải, không phải cả Đà Nẵng. `resources/content/itinerary/place-exclusions.json:5-10`. |
| User exclusion | `!array_intersect($excluded, [id,provider_place_id,source_place_id])`. `app/Services/Itinerary/FixedSlotPlanner.php:18-19`. | **HẾT POI**; form mới không gửi excluded_ids. `public/vendor/aurel-ai/planner.js:92-95`. |
| Place/cluster dedupe | `samePlace()` theo IDs, alias/name, ≤300m hoặc biến thể tên/cùng cụm ≤250m; proximity đơn thuần không gộp restaurant khác nhau. `app/Services/Itinerary/PlanningPolicy.php:14-34`. | Có thể **HẾT POI** khi tất cả trùng `_exclude_places`; dedupe nội bộ một pool không rỗng thường còn đại diện. |
| **Giờ bắt buộc** | `foreach ... opening_schedule=policy->schedule(...)`; `array_filter(... !empty(opening_schedule))`. `app/Services/Itinerary/FixedSlotPlanner.php:21-22`. | **HẾT POI — nguyên nhân chính có bằng chứng R1–R3.** Không được nhầm với filter giá. |
| Parser giờ text | Không có text→NULL; gặp từ `chua/xac minh/kiem tra/tuy/thay doi/su kien/khoang/lien he/theo lich`, sai định dạng giờ/day→NULL. `app/Services/Places/ReferenceHours.php:10-36`. | **HẾT POI** có text mơ hồ; không parse ngôn ngữ tự nhiên hoặc chuỗi OSM tùy ý. |
| Slot scope/category | `!inScope()`, night không nightEligible, `definition.category!==category(place)` → NULL. `app/Services/Itinerary/DailySlotPolicy.php:57-70`. | **HẾT POI cho slot**; không đủ food có thể trống Trưa/Tối dù Sáng/Chiều có visit. |
| Invalid schedule | `!$schedule || !empty($schedule['issue']) → null`. `app/Services/Itinerary/DailySlotPolicy.php:70`. | **HẾT POI**; timezone/rule unknown cũng không cho within_hours qua OpeningHours. `app/Services/Places/OpeningHours.php:15-17`, `app/Services/Places/OpeningHours.php:122-131`. |
| Arrival/departure | Begin=max(slot.start,scope.start,arrival,earliest); limit=min(slot.end,scope.end,departure). `app/Services/Itinerary/DailySlotPolicy.php:71-79`. | **HẾT POI** nếu không còn khoảng đủ dài; stay own_accommodation tự nó không loại tham quan. |
| Duration fit | `end=clock+duration`; vượt limit thì break (ngoại lệ night có quy tắc riêng); thử bước 5 phút. `app/Services/Itinerary/DailySlotPolicy.php:86-90`. | **HẾT POI** nếu tất cả experience dài hơn các khung cho phép. |
| Coverage | `OpeningHours::check(...).status !== 'within_hours'` → không nhận. Bao trùm toàn khoảng ghé, qua đêm, breaks, exception ưu tiên. `app/Services/Itinerary/DailySlotPolicy.php:91-95`, `app/Services/Places/OpeningHours.php:96-131`. | **HẾT POI**, kể cả unknown; unknown không phải closed nhưng cùng không đủ điều kiện auto-schedule. |
| **Provisional 7 ngày** | `$checkDates=...range(0,6)` và vòng foreach chỉ cần một check không within_hours là false. `app/Services/Itinerary/DailySlotPolicy.php:80-94`. | **HẾT POI** nếu mỗi POI có bất kỳ ngày nghỉ/thiếu giờ; chưa phải nguyên nhân đầu tiên khi schedule đã 0. |
| Used across days | `!isset($used[provider_place_id])`; đánh dấu used khi activity được chấp nhận. `app/Services/Itinerary/FixedSlotPlanner.php:41`, `app/Services/Itinerary/FixedSlotPlanner.php:125`, `app/Services/Itinerary/FixedSlotPlanner.php:140`. | Có thể **HẾT POI các ngày sau**; không giải thích riêng việc ngày 1 cũng 0 khi nguồn có candidates đủ điều kiện. |
| Interests | `str_contains(text,normalize(interest)) → score++`. `app/Services/Itinerary/PlanningPolicy.php:98-104`. | **MỀM**, không hard filter. Food restriction/requirements truyền prompt; không tìm thấy bộ validator deterministic riêng cho chúng trong materialize. `app/Services/Itinerary/FixedSlotPlanner.php:68-70`, `app/Services/Itinerary/FixedSlotPlanner.php:124-141`. |
| Giá/ngân sách | `$cap = budget===null ? null : floor(budget*1.10)`; `if($quote && $cap!==null && spent+amount>$cap) continue`. `app/Services/Itinerary/FixedSlotPlanner.php:129-132`. | **HẾT POI** nếu mọi lựa chọn có quote vượt cap; **không kích hoạt** với budget NULL. Quote NULL không bị loại ở đây. |
| Distance/travel | `earliest=lastEnd+30`; travel time/distance=NULL. `app/Services/Itinerary/FixedSlotPlanner.php:126-139`. | Khoảng đệm có thể làm hết chỗ trong slot; **không tìm thấy max-distance hard filter hoặc route-matrix feasibility filter trong planner hiện hành**. Geography boundary và dedupe distance là hai cơ chế khác. |
| AI validation | ID lạ/trùng, sai slot/dates/reason, meal/night>1, visit>pace.max → issues/retry/error. `app/Services/Itinerary/FixedSlotPlanner.php:93-113`. | **LỖI** sau hai lần; AI chọn các mảng trống được chấp nhận nếu structure đúng. |
| Pace min | `if($visits<$pace['min']) $warnings[] = ...`. `app/Services/Itinerary/FixedSlotPlanner.php:149`. | **MỀM**; không loại POI, không chặn response 201 hoặc Save. |

Các đoạn quyết định liên tiếp, trích đúng code gốc để thấy điểm mất dữ liệu:

```php
// app/Services/Itinerary/GeoapifyClient.php:82 — field của provider
'opening_hours'=>$p['opening_hours'] ?? null, 'opening_hours_verified'=>false, 'price_status'=>'unknown',

// app/Services/Itinerary/DailySlotPolicy.php:41,47 — các field policy thực sự đọc
if(!empty($place['opening_schedule']))return $place['opening_schedule'];
// ... nhánh regular_opening ở dòng 42–46 ...
return app(ReferenceHours::class)->schedule($place['hours_text'] ?? null,$place['source_url'] ?? null,$place['source_checked_at'] ?? null);

// app/Services/Itinerary/FixedSlotPlanner.php:21-22 — hard filter
foreach($places as &$place)$place['opening_schedule']=$this->policy->schedule($place);unset($place);
$places=array_values(array_filter($places,fn($p)=>!empty($p['opening_schedule'])));
```

Không thấy đoạn chuyển `opening_hours→opening_schedule` trong ba file trên. Ngay cả bổ sung chuyển field này cũng chưa đủ cho snapshot R3 vì raw opening_hours của cả 20 POI đang rỗng; cần tách vấn đề **thiếu nguồn giờ** và **adapter chưa nhận field**. `app/Services/Itinerary/GeoapifyClient.php:80-87`, `app/Services/Itinerary/DailySlotPolicy.php:39-47`, `docs/itinerary-analysis.md:18`.

## 6. Nguồn gốc các chuỗi placeholder và đường lưu

| Chuỗi người dùng nêu | Kết quả tìm chính xác | Vị trí/state |
| --- | --- | --- |
| “chưa đủ dữ liệu để xác định…” | **Không tìm thấy nguyên văn**. Source hiện có là “chưa đủ địa điểm đúng loại, giờ tham khảo, thời lượng hoặc ngân sách phù hợp; để trống khung này.” | Hard-code `app/Services/Itinerary/FixedSlotPlanner.php:147`; response replay cũng dùng “địa điểm”, xem `docs/itinerary-analysis.md:30`. |
| “để trống khung này” | Tìm thấy, nằm trong cùng câu cảnh báo trên. | Khi `$accepted=[]`, thêm vào `$warnings`, không thêm `$activities`. `app/Services/Itinerary/FixedSlotPlanner.php:123-147`. |
| “chưa có giá nguồn” | Có **cụm này trong câu dài** “Chi phí ăn uống, lưu trú và di chuyển chưa có giá nguồn thì chưa nằm trong tổng tạm tính.”; không tìm thấy một activity placeholder riêng chỉ có nhãn đó. | `app/Services/Itinerary/PlanningPolicy.php:115`; warnings này được thêm vô điều kiện, kể cả 0 activity. |
| “chỉ xếp được 0 điểm…” | “0” không hard-code; ghép biến `$visits`. Chỉ đếm activity accepted ở Sáng/Chiều. | `app/Services/Itinerary/FixedSlotPlanner.php:141`, `app/Services/Itinerary/FixedSlotPlanner.php:149`. |
| “chưa nằm trong tổng tạm tính” | Tìm thấy trong PlanningPolicy; UI có biến thể “chưa được cộng vào tổng tạm tính”. | `app/Services/Itinerary/PlanningPolicy.php:115`, `app/Services/Itinerary/BudgetCalculator.php:133`, `public/vendor/aurel-ai/planner.js:262`, `public/vendor/aurel-ai/chat.js:248`. |

Đường gắn state là `materialize.warnings → FixedSlotPlanner.result.warnings → ConversationalPlanner.prepared.warnings/assumptions → controller.planning_warnings → trip.generation_warnings → tripPlanningNotes`. Cảnh báo không phải name/title của một POI, không phải một “hoạt động rỗng” để đếm pace. `app/Services/Itinerary/FixedSlotPlanner.php:28-35`, `app/Services/Itinerary/ConversationalPlanner.php:96-99`, `app/Http/Controllers/Api/V2/ItineraryPlannerController.php:87`, `public/vendor/aurel-ai/planner.js:363`, `public/vendor/aurel-ai/planner.js:259-263`.

**Có lưu DB, nhưng cần phân biệt nơi lưu:**

- `itinerary_item` chỉ ghi các activity trong `prepared.activities`; không ghi các warnings thành activity. Bản khớp tình huống có 0 item. `app/Services/Itinerary/GenerationService.php:47-71`, `docs/itinerary-analysis.md:21`.
- Nguyên response có warnings được writer lưu trong `itinerary_write_request.response_body`, nên những câu này **đã được lưu DB trong replay response**, kể cả khi itinerary.planning_metadata=NULL. `app/Services/Itinerary/IdempotentWriter.php:34-37`, `docs/itinerary-analysis.md:21`, `docs/itinerary-analysis.md:30`.
- FixedSlotPlanner tạo `planning_metadata` đầy đủ, nhưng ConversationalPlanner ghép `$prepared` chỉ lấy title/region/activities/warnings, làm rơi metadata. `GenerationService::persist()` nhận metadata NULL; PlanningMetadata return sớm khi chưa có policy. Vì vậy đường form hiện tại không lưu đúng metadata slot vào itinerary, rồi mở lại không có cùng warnings ở `data.planning_warnings`. `app/Services/Itinerary/FixedSlotPlanner.php:24-35`, `app/Services/Itinerary/ConversationalPlanner.php:94-99`, `app/Services/Itinerary/GenerationService.php:73`, `app/Services/Itinerary/PlanningMetadata.php:14-15`, `app/Http/Resources/ItineraryTripResource.php:34-36`; R4 xác nhận metadata NULL.
- Đường v2 generate trực tiếp nhận `$prepared` từ GenerationService, không qua lớp ghép này; có khả năng lưu metadata. PlanningMetadata rebuild đổi wording thành “khung giờ chưa có địa điểm phù hợp” / “chưa đủ điểm tham quan theo pace…”, không giữ nguyên toàn bộ câu ban đầu. `app/Http/Controllers/Api/V2/ItineraryController.php:62-66`, `app/Services/Itinerary/PlanningMetadata.php:35-42`.
- Trong chat, prepared/assumptions nằm trong context_json và assistant timeline nằm trong chat_message.entities_json; chat replay cũng được writer lưu. Editor proposal không tự ghi itinerary, nhưng hội thoại vẫn được lưu. `app/Services/Itinerary/TravelAssistantService.php:155-169`, `app/Services/Itinerary/ConversationalPlanner.php:134`, `app/Services/Itinerary/ConversationalPlanner.php:179-188`.

Trên board hiện hành không có renderer tạo bốn thẻ slot cố định từ planning_slots. Các câu có tên slot là notes phía trên; vùng ngày chỉ duyệt `day.items` và empty-state “Một ngày còn để ngỏ…”. Do không có ảnh chụp DOM từ người dùng, không khẳng định cấu trúc DOM thực tế của tab họ đang mở khác code này; có thể đối chiếu bằng DevTools như §10. `public/vendor/aurel-ai/planner.js:257-277`, `public/vendor/aurel-ai/planner.js:278-309`.

## 7. Xử lý giá và ngân sách

### 7.1. Tạo quote

`PlanningPolicy::quote()` chỉ gọi `VisitInformation::estimate(place.ticket, party)`. Không tự lấy `price_min/price_max` legacy hoặc `price_reference` Sheet làm vé cả nhóm. Ticket NULL/quá review_due → quote NULL; ticket free có nguồn → amount=0 known; ticket priced cần adult_reference integer, nhân adults, cộng trẻ em theo chiều cao phù hợp, đánh dấu trẻ chưa xác định thay vì đoán. `app/Services/Itinerary/PlanningPolicy.php:76-79`, `app/Services/Itinerary/VisitInformation.php:28-45`.

Enrich ticket/regular_opening chỉ xảy ra khi location/name match, tọa độ trong radius, ngày hiện tại nằm trong [reviewed_on,review_due] và có ticket. JSON có review_due chung 11/11/2026, riêng Suối Tiên 12/11/2026; đến ngày phân tích 16/11 cả hai hạn đã qua. Không phải nguồn bổ sung hợp lệ cho Đà Nẵng, và các giá cũ không thể được dùng như đang được xác minh hôm nay. `app/Services/Itinerary/VisitInformation.php:17-23`, `resources/content/itinerary/visit-facts.json:2-3`, `resources/content/itinerary/visit-facts.json:41-47`.

Giá Sheet được giữ dưới `{min,max,unit}` để tham khảo; không có `ticket` trong presenter của Sheet, nên mặc định không vào công thức quote nêu trên. Giá NULL không phải free. `app/Services/Places/SheetPlaceCatalog.php:68-77`, `app/Services/Itinerary/PlanningPolicy.php:78`.

### 7.2. Tổng tạm tính nằm ở đâu

- Trong proposal chat: `ConversationalPlanner::present()` cộng `ticket_estimate.amount`, đếm known/unpriced/unknown children; status chỉ over_budget nếu limit khác NULL và tổng vượt limit, còn lại incomplete. `app/Services/Itinerary/ConversationalPlanner.php:155-161`, `app/Services/Itinerary/ConversationalPlanner.php:179-184`.
- Khi persist AI: mỗi POI có cost_line activity, unit group, quantity=1, amount/status/source từ quote; `estimated_cost` trong itinerary_item để NULL, quote nằm trong snapshot. Trẻ chưa có giá có thể tạo thêm cost_line unknown. `app/Services/Itinerary/GenerationService.php:55-70`.
- Khi lưu chỉnh sửa thủ công: field estimated_cost NULL tạo cost_line unknown; có số tạo estimated, unit group, source “Khách tự nhập”. Với item AI có sẵn không đổi place và cả giá cũ/mới đều NULL, nhánh này giữ cost_line cũ; không phải mọi lần Save đều xóa giá nguồn. `app/Services/Itinerary/PlannerService.php:178-183`.
- Trong resource đã lưu: BudgetCalculator cộng các cost_line known/estimated có amount hợp lệ, nguồn, currency VND, unit phù hợp, quantity≥1; amount×quantity. NULL/invalid/unknown đi vào unknown_count, không biến thành một dòng giá known 0. `app/Services/Itinerary/BudgetCalculator.php:43-59`, `app/Services/Itinerary/BudgetCalculator.php:142-148`.
- Ngày chưa planned tính free_day_allowance nếu có, nếu NULL ghi unknown; ngày selected chưa generate thêm unknown; contingency NULL thêm unknown. Có chỗ ở riêng thì bỏ lodging lines và không tính uncovered hotel nights. `app/Services/Itinerary/BudgetCalculator.php:45-46`, `app/Services/Itinerary/BudgetCalculator.php:62-105`.
- Tổng `accounted_total=known_total+estimated_total` với kiểm tra overflow. Budget NULL luôn status incomplete; missing prices cũng incomplete khi chưa vượt budget. `app/Services/Itinerary/BudgetCalculator.php:108-139`.

Trong snapshot R4, total=0 vì không có cost_line của activity và các khoản còn lại chưa có giá, **không phải chuyến đi miễn phí**; unknown_count=5 và complete=false phản ánh điều đó. `docs/itinerary-analysis.md:21`, `app/Services/Itinerary/BudgetCalculator.php:72-85`, `app/Services/Itinerary/BudgetCalculator.php:124-139`.

### 7.3. Vì sao xuất hiện “chưa nằm trong tổng tạm tính”

PlanningPolicy luôn thêm câu coverage tại dòng 115, không kiểm tra “người dùng đã có chỗ ở” hay “có activity hay không”. Vì vậy câu có lưu trú vẫn có thể hiện với own_accommodation và budget NULL. Frontend nhận nguyên warnings, không tạo lỗi về budget ở đây. `app/Services/Itinerary/PlanningPolicy.php:107-116`, `app/Services/Itinerary/FixedSlotPlanner.php:33`, `public/vendor/aurel-ai/planner.js:259-263`, `docs/itinerary-analysis.md:30`.

Board hiển thị tiền từng item qua `estimated_cost ?? ticket_estimate.amount`; null không in số tiền. Khi dirty và có budget, board tính tổng nhanh từ các field này, với `??0` chỉ để tính tổng phần đã biết. Chat chỉ hiển thị ticket_total khi có known ticket hoặc total>0. Không tìm thấy khối tổng toàn chuyến trên board tương đương BudgetCalculator; tổng chi tiết hiện có ở API/budget và phần chat. `public/vendor/aurel-ai/planner.js:260-262`, `public/vendor/aurel-ai/planner.js:289-290`, `public/vendor/aurel-ai/chat.js:237-248`.

## 8. Frontend state và logic lưu/hoàn tác

### 8.1. State

State editor nằm trong closure JS: `trip`, `undo`, `version`, `dirty`, `busy`, `editing`, destinations/restrictions; không tìm thấy Redux/Pinia hay persistence localStorage cho object itinerary ở file này. localStorage tại đây chỉ đọc token. `public/vendor/aurel-ai/planner.js:14-16`, `public/vendor/aurel-ai/planner.js:70`.

`trip.days=[{items:[...]}]`; context() serialize fields được phép để gửi save/chat. `remember()` deep-clone trip bằng JSON, bỏ id/revision trong snapshot undo, giới hạn 60; undo khôi phục nội dung nhưng giữ ID/revision hiện hành, đánh dirty=true và tăng version. Không có request rollback DB trong thao tác Hoàn tác. `public/vendor/aurel-ai/planner.js:9`, `public/vendor/aurel-ai/planner.js:103-114`, `public/vendor/aurel-ai/planner.js:140-147`.

### 8.2. Nút “Lưu lịch trình” và “Đã lưu”

Nút Save chỉ disabled khi busy/readOnly; không kiểm tra tổng activity>0 hoặc đạt pace. `save()` lấy context, gửi PUT `/{id}/planner` kèm expected_revision nếu đã có ID, hoặc POST `/planner` nếu chưa; sau response map resource và dirty=false. `public/vendor/aurel-ai/planner.js:267-269`, `public/vendor/aurel-ai/planner.js:383-395`.

Backend PlannerRequest cho phép `days[*].items=[]`, chỉ giới hạn max50, không min1; số ngày phải khớp, id activity không trùng, end_time phải sau start nếu không overnight. PlannerService.save đòi days tồn tại nhưng không đòi có activity; có owner/revision/status guard; ghi status saved và tăng revision. `app/Http/Requests/Itinerary/PlannerRequest.php:29-42`, `app/Http/Requests/Itinerary/PlannerRequest.php:62-75`, `app/Services/Itinerary/PlannerService.php:69-84`.

Nhãn **“Đã lưu”** dùng biểu thức `dirty ? ... : trip.id ? 'Đã lưu' : 'Chưa lưu'`; không so status với saved và không nhìn số hoạt động. Generate đã tạo bản draft có ID nên vừa tạo lịch rỗng cũng hiện nhãn này. Trong library, cùng record lại được ghi “Bản nháp” nếu status khác saved. Đây là hai định nghĩa “lưu” không đồng nhất trên UI. `public/vendor/aurel-ai/planner.js:264`, `public/vendor/aurel-ai/planner.js:418`, `app/Services/Itinerary/TripService.php:22`, `docs/itinerary-analysis.md:21`.

### 8.3. Áp dụng/Bỏ qua, Undo qua save, mở lại/PDF

Áp dụng proposal kiểm tra version, cùng điểm đến, đủ ngày hợp lệ, day_trip, items là array≤50, window hợp lệ, closure. Không đòi `items.length>0`; do đó proposal có đủ ngày nhưng rỗng vẫn có thể thay nội dung. Có remember() trước thay, chỉ target ngày/buổi khi có scope, toàn lịch có thể đổi duration. Bỏ qua chỉ ghi quyết định trong object message UI. `public/vendor/aurel-ai/planner.js:454-499`.

Sau Save, JS loại ID không còn tồn tại khỏi các snapshot undo, để phục hồi item đã xóa thành bản mới thay vì gửi ID đã mất. Mở library lấy GET v2 và legacy, mở lịch thì dirty=false, undo=[]; lịch legacy chỉ xem. PDF nếu đang dirty/chưa có ID sẽ gọi save() trước rồi GET PDF, nên tải PDF cũng có thể ghi lịch. `public/vendor/aurel-ai/planner.js:388-402`, `public/vendor/aurel-ai/planner.js:411-435`.

Idempotency bảo vệ retry khỏi ghi trùng, revision chống ghi đè phiên khác. Tuy nhiên response replay là bản cũ; keyFor ghi nhớ key theo action+JSON payload trong cùng trang. Lặp tạo cùng payload trong trang có thể nhận lại lịch rỗng cũ, kể cả sau khi source được cải thiện; nút Tạo lịch mới xóa map identities. `public/vendor/aurel-ai/planner.js:56-60`, `public/vendor/aurel-ai/planner.js:375-380`, `app/Services/Itinerary/IdempotentWriter.php:10-16`, `app/Services/Itinerary/WriteGuard.php:16-19`.

## 9. Các lỗi/rủi ro phát hiện

Đánh giá dưới đây tách lỗi đã có bằng chứng tĩnh/runtime khỏi rủi ro cần tái hiện. Không có thay đổi sửa chữa trong phiên này.

| Mức | Phát hiện | Bằng chứng và giới hạn |
| --- | --- | --- |
| Cao — xác nhận | **Thiếu giờ + hard filter làm rỗng toàn bộ pool.** Không có thông điệp riêng “nguồn giờ không sẵn sàng”; nhiều slot dùng chung cảnh báo gồm cả category/duration/budget. | `app/Services/Itinerary/FixedSlotPlanner.php:21-22`, `app/Services/Itinerary/FixedSlotPlanner.php:147`; nguồn giờ 0 ở `docs/itinerary-analysis.md:12`, `docs/itinerary-analysis.md:15`, `docs/itinerary-analysis.md:18`. |
| Cao — xác nhận | **Không có activity vẫn báo generate thành công và ghi DB.** Thiếu guard hoặc trạng thái partial/empty riêng. | `$plan=null` vẫn materialize: `app/Services/Itinerary/FixedSlotPlanner.php:58-90`; controller luôn create/201: `app/Http/Controllers/Api/V2/ItineraryPlannerController.php:84-87`; notice “sẵn sàng” ở `public/vendor/aurel-ai/planner.js:371`; bản 201 rỗng ở `docs/itinerary-analysis.md:21`. |
| Cao — xác nhận | **Mismatch schema giờ.** Geo raw `opening_hours` không được policy đọc; approved public POI bỏ open_time/close_time, dù DB có thể có giờ. | `app/Services/Itinerary/GeoapifyClient.php:82`, `app/Services/Itinerary/DailySlotPolicy.php:39-47`, `app/Services/Itinerary/TravelCatalog.php:25-35`. R3 raw giờ cũng thiếu, nên chỉ sửa adapter không tự có nguồn giờ. |
| Cao — xác nhận | **Mất planning_metadata qua ConversationalPlanner.** Mất policy/slots/ai_used, lưu/mở lại/PDF không có cùng cảnh báo; proposal khó mang planning_reference như thiết kế. | Khởi tạo metadata `app/Services/Itinerary/FixedSlotPlanner.php:24-35`; ghép không copy `app/Services/Itinerary/ConversationalPlanner.php:94-99`; persist/rebuild `app/Services/Itinerary/GenerationService.php:73`, `app/Services/Itinerary/PlanningMetadata.php:14-15`; UI chỉ gắn reference khi có metadata `public/vendor/aurel-ai/planner.js:485`; R4 xác nhận NULL. |
| Cao — logic xác nhận, tác động tùy POI | **Lịch chưa chốt ngày dùng AND cả bảy thứ.** Một ngày nghỉ/không có giờ khiến POI bị loại khỏi mọi ngày provisional, dù ngày đi sau này có thể phù hợp. | `app/Services/Itinerary/DailySlotPolicy.php:80-95`. Chưa cần bước này để giải thích R3, vì POI đã bị loại trước ở filter schedule. |
| Cao — dữ liệu xác nhận | **POI legacy gắn location khác phân loại trong seed hiện tại; data public quá ít.** | 9 dòng thuộc nhóm HCM theo seed nhưng đang location Đà Nẵng, `docs/itinerary-analysis.md:27`, `database/seeders/AttractionSeeder.php:159-290`. Không kết luận nguồn lịch sử gây lỗi; public query hiện loại chúng, nên đây không phải quyền tự bật/chuyển dữ liệu. |
| Trung bình — xác nhận | **State planning không được lưu đầy đủ ở đường compact create.** PlannerService tạo TripService bằng fields cơ bản, không truyền transport/pace/windows của plan; activity_window_confirmed=false. | `app/Services/Itinerary/PlannerService.php:54-61`, `app/Services/Itinerary/TripService.php:67-74`; R4 transport/pace NULL. Có thể gây lệch thông tin khi mở lại; không phải nguyên nhân filter ban đầu, vì plan được tính trước create. |
| Trung bình — xác nhận | **UI “Đã lưu” không phản ánh status saved/độ đầy đủ.** Save/Apply chấp nhận 0 activity. | `public/vendor/aurel-ai/planner.js:264-268`, `public/vendor/aurel-ai/planner.js:467`, `app/Http/Requests/Itinerary/PlannerRequest.php:31`, `app/Services/Itinerary/PlannerService.php:74-82`. |
| Trung bình — xác nhận | **Pace min chỉ trong prompt/warning, không validator.** AI có thể trả đủ schema nhưng mọi slot rỗng; `issues()` không chặn thiếu min; materialize có thể giảm số điểm thêm sau kiểm tra. | Prompt `app/Services/Itinerary/FixedSlotPlanner.php:67`; validator `app/Services/Itinerary/FixedSlotPlanner.php:93-111`; skip/cảnh báo `app/Services/Itinerary/FixedSlotPlanner.php:125-149`. Không đề xuất ép đủ số điểm bằng dữ liệu giả. |
| Trung bình — xác nhận | **Không refill sau skip vì giờ/budget** và facts gửi AI thiếu giá từng candidate. | `app/Services/Itinerary/FixedSlotPlanner.php:62-70`, `app/Services/Itinerary/FixedSlotPlanner.php:127-132`. AI thấy budget tổng nhưng không có thông tin đủ để tự chọn theo quote; backend chỉ bỏ điểm vượt cap. |
| Trung bình — xác nhận | **Yêu cầu giảm số điểm đặt `_min_stops/_max_stops` nhưng fixed planner không dùng.** Hàm stops còn tồn tại với tham số này. | `app/Services/Itinerary/ConversationalPlanner.php:80-81`, `app/Services/Itinerary/PlanningPolicy.php:37-47`; FixedSlotPlanner dùng pace tại `app/Services/Itinerary/FixedSlotPlanner.php:50`, `app/Services/Itinerary/FixedSlotPlanner.php:118`. Không tìm thấy nơi gọi stops trong luồng mới. |
| Trung bình — logic xác nhận | **Food restrictions/requirements chủ yếu dựa prompt, chưa có hard validator riêng.** | Truyền prompt `app/Services/Itinerary/FixedSlotPlanner.php:68-70`; kiểm tra chỉ ID/slot/reason/max tại `app/Services/Itinerary/FixedSlotPlanner.php:93-111`. Không kết luận POI cụ thể vi phạm khi chưa có dữ liệu hạn chế. |
| Trung bình — xác nhận | **Giờ enrich bị buộc vào ticket còn hạn.** Khi thiếu/expired ticket, cả regular_opening cũng không được gắn dù dữ liệu giờ độc lập có thể vẫn hữu ích. JSON hạn review đã qua. | `app/Services/Itinerary/VisitInformation.php:20-23`, `resources/content/itinerary/visit-facts.json:3`, `resources/content/itinerary/visit-facts.json:42`. Với Đà Nẵng không có record nên không phải nguồn gốc riêng. |
| Trung bình — xác nhận | **Clamp duration trái với cách hiểu “giữ nguyên thời lượng nguồn”.** Suggested duration bị làm tròn và cắt 30–360; lớn hơn slot 210 phút dễ không có morning/afternoon. | `app/Services/Itinerary/PlanningPolicy.php:57`, `app/Services/Itinerary/DailySlotPolicy.php:88`; yêu cầu giữ nguồn tại `docs/itinerary-approved-requirements.md:133-135`. Chưa chạy case duration dài. |
| Trung bình — xác nhận | **Thông điệp tuyến không nhất quán.** Fixed planner luôn allowance/unverified nhưng non-Sheet timeline warning còn nói thời gian tuyến lấy Geoapify; PlanningMetadata rebuild luôn thêm notice 30 phút kể cả non-Sheet. | `app/Services/Itinerary/FixedSlotPlanner.php:138-139`, `app/Services/Itinerary/ConversationalPlanner.php:188`, `app/Services/Itinerary/PlanningMetadata.php:41`. |
| Trung bình — xác nhận | **Warnings biến mất sau chỉnh sửa UI.** render() bỏ cả budget.warnings/generation_warnings khi dirty, chỉ tính lại warning vượt ngân sách; slot trống vẫn có thể còn. | `public/vendor/aurel-ai/planner.js:259-263`; metadata bị mất làm thiếu đường phục hồi sau reopen, xem phát hiện metadata ở trên. |
| Trung bình — logic xác nhận | **Merge public thay Geo có thể làm mất giờ của cùng POI.** Chỉ mượn coordinates/wikidata rồi đánh dấu Geo used; không merge opening_hours/schedule đầy đủ. | `app/Services/Itinerary/TravelCatalog.php:64-73`. Chưa xác nhận có POI cụ thể bị mất giờ vì R3 không có giờ raw. |
| Trung bình — xác nhận | **Fixture/test chưa theo contract fixed slots hiện tại.** Fixture vẫn trả `days:[{date,items}]`, đọc stops_per_day/budget_context, thiếu giờ trong POI, trong khi planner đòi days object/5 slot arrays và schedule. | `tests/Feature/ItineraryPlannerTest.php:114-123`, `tests/Feature/ItineraryPlanningPolicyTest.php:30-55`, `tests/Feature/ItineraryGeoapifyTest.php:42-58` so với `app/Services/Itinerary/FixedSlotPlanner.php:62-70`, `app/Services/Itinerary/FixedSlotPlanner.php:95-106`. **Chưa chạy test**, không báo pass/fail. Không tìm thấy test tham chiếu FixedSlotPlanner/DailySlotPolicy/fixed_slots trong cây tests lúc rà. |
| Thấp/trung bình — logic xác nhận | **Night có thể qua nửa đêm, không có hard end tổng ngày trừ departure/scope.** | `app/Services/Itinerary/DailySlotPolicy.php:72`, `app/Services/Itinerary/DailySlotPolicy.php:88-90`. Chưa tái hiện overlap với ngày kế tiếp. |
| Thấp — xác nhận | **Dead legacy fallback và thông báo mojibake.** Controller cũ còn buildFallback/template, nhưng generate return409 và mutation bị WriteGuard chặn trước logic cũ; không phải fallback tạo ra lỗi đang xét. | `app/Http/Controllers/ItineraryController.php:47-52`, `app/Http/Controllers/ItineraryController.php:72`, `app/Http/Controllers/ItineraryController.php:325-331`, `app/Services/Itinerary/WriteGuard.php:10-13`. |

### Error handling và hard-code cần biết

Geoapify phân biệt thiếu key, network, access denied/quota/invalid response; không nuốt lỗi rồi tự tạo lịch giả ở client này. Groq planner phân biệt key/model/quota/network, retry theo điều kiện, không tự đổi model. Ngược lại, không có candidate không đi vào error handling của provider mà đi thẳng success rỗng. `app/Services/Itinerary/GeoapifyClient.php:190-207`, `app/Services/Itinerary/GroqPlannerClient.php:14-35`, `app/Services/Itinerary/FixedSlotPlanner.php:59-90`.

Chat JSON path có thể trả HTTP200 kèm code khi provider lỗi; SSE cũng khởi tạo stream HTTP200 rồi phát event error. Vì thế khi debug chat cần nhìn body/code/event, không chỉ status HTTP. Đường form planner/generate không dùng helper aiUnavailable này. `app/Http/Controllers/ChatController.php:66-72`, `app/Http/Controllers/ChatController.php:95-114`, `app/Http/Controllers/Api/V2/ItineraryPlannerController.php:77-91`.

Hard-code trong thuật toán: slot giờ cố định, normal min3/max4, tuần neo 02/11/2026, allowance30 phút, soft cap110%, candidate limit18, chunk3 và chunk5, prompt tiếng Việt và câu cảnh báo gộp nhiều nguyên nhân. Đây là các hằng số có tác động thực tế, không phải TODO chưa được dùng. `app/Services/Itinerary/DailySlotPolicy.php:12-22`, `app/Services/Itinerary/DailySlotPolicy.php:84`, `app/Services/Itinerary/PlanningPolicy.php:11`, `app/Services/Itinerary/FixedSlotPlanner.php:26`, `app/Services/Itinerary/FixedSlotPlanner.php:50`, `app/Services/Itinerary/FixedSlotPlanner.php:130`, `app/Services/Itinerary/ConversationalPlanner.php:94`.

**TODO/FIXME/HACK/XXX:** không tìm thấy các marker này trong services Itinerary/Places, requests Itinerary, controllers itinerary/chat, JS `public/vendor/aurel-ai`, view itinerary/PDF khi rà. Không suy diễn “không có marker” thành “không có lỗi”; các lỗi trên được suy ra từ code thực thi và số liệu R.

## 10. Giả thuyết nguyên nhân gốc và các bước tự debug

### 10.1. Xếp hạng khả năng, kèm kiểm chứng phân biệt

| Hạng | Giả thuyết | Mức bằng chứng | Cách kiểm chứng nhanh |
| --- | --- | --- | --- |
| 1 | **Nguồn POI Đà Nẵng thiếu giờ mà planner bắt buộc có schedule.** | **Rất cao, chuỗi bằng chứng đủ để giải thích kết quả:** R3 có20 POI nhưng 0 giờ; R2 không có source Đà Nẵng; JSON không có Đà Nẵng; public mapper không cấp giờ. `docs/itinerary-analysis.md:18`, `docs/itinerary-analysis.md:15`, `resources/content/itinerary/visit-facts.json:5-48`, `app/Services/Itinerary/TravelCatalog.php:25-35`, `app/Services/Itinerary/FixedSlotPlanner.php:21-22`. | Đếm pool trước/sau dòng22, thống kê có schedule theo provider. Kỳ vọng sau22=0 với snapshot này; số sau boundary/merge cần đo, không lấy20 làm con số cho mọi bước. |
| 2 | **Adapter/source contract giờ không thống nhất.** | Cao về lỗi code; tác động bổ sung, **không đủ tự giải thích hoặc tự chữa thiếu raw hours**. Geo lưu opening_hours; policy đọc field khác; local open_time/close_time bị bỏ. `app/Services/Itinerary/GeoapifyClient.php:82`, `app/Services/Itinerary/DailySlotPolicy.php:39-47`, `app/Services/Itinerary/TravelCatalog.php:25-35`. | So bốn field giờ của từng provider ở trước và sau enrich. Nếu opening_hours có nhưng schedule NULL thì xác nhận adapter; nếu cả hai NULL như R3 thì phải giải quyết nguồn giờ trước. |
| 3 | **Success/Save không yêu cầu activity, biến lỗi thiếu dữ liệu thành màn hình “sẵn sàng/đã lưu”.** | Rất cao về cơ chế UI triệu chứng; không tạo ra thiếu giờ, nhưng cho phép lỗi tồn tại như một lịch hợp lệ. `app/Services/Itinerary/FixedSlotPlanner.php:90`, `app/Http/Controllers/Api/V2/ItineraryPlannerController.php:84-87`, `public/vendor/aurel-ai/planner.js:264`, `public/vendor/aurel-ai/planner.js:371`, `docs/itinerary-analysis.md:21`. | Xem response số items và status/readiness cùng lúc; đối chiếu DB và UI. |
| 4 | **Provisional đòi cùng giờ mở cả bảy thứ.** | Trung bình cho nguyên nhân còn lại sau khi đã có schedule; chắc chắn tồn tại ở code. `app/Services/Itinerary/DailySlotPolicy.php:80-95`. | Cùng POI/scope, so provisional không ngày với ngày thật có source hours; ghi status mỗi weekday. POI nghỉ một ngày phải hiện rõ là bị loại bởi chính sách này. |
| 5 | **Catalogue local không đủ/metadata/tọa độ/name mismatch; dữ liệu legacy sai location.** | Trung bình cho số lượng local bị mất; thấp hơn cho nguyên nhân duy nhất vì Geo cache đã có nhiều POI. `docs/itinerary-analysis.md:12`, `docs/itinerary-analysis.md:27`, `app/Services/ExperienceCatalogService.php:21-28`, `app/Services/Itinerary/TravelCatalog.php:64-66`. | Đếm all→public→coordinate-match→inside-boundary; xem canonical name Geo và aliases/name local trước merge. |
| 6 | **Các POI có giờ nhưng không vừa slot/duration hoặc thiếu food.** | Có khả năng theo code, chưa có POI đủ schedule để chứng minh là bước đầu gây lỗi này. `app/Services/Itinerary/DailySlotPolicy.php:68-95`. | Sau khi schedule>0, đếm hợp lệ theo từng date/slot và nhóm lý do wrong_category/duration/outside_hours/unknown. |
| 7 | **Replay/cache giữ kết quả rỗng cũ qua các lần thử.** | Có cơ chế rõ, một response rỗng đang nằm trong replay; chưa xác nhận người dùng đã retry cùng key. `app/Services/Itinerary/IdempotentWriter.php:10-16`, `public/vendor/aurel-ai/planner.js:56-60`, `docs/itinerary-analysis.md:21`. | Xác định request có đi qua prepare hay return replay; đối chiếu timestamp/cache TTL; dùng một request key mới trên DB thử riêng nếu cần generate lại. Không dùng cache:clear/flush toàn hệ thống để phân biệt. |
| 8 | **Closure/category/interests/budget loại hết.** | Thấp trong tình huống này: closure JSON chỉ một địa điểm, DB exclusion Đà Nẵng trống; interests không hard filter; budget NULL vô hiệu cap. `resources/content/itinerary/place-exclusions.json:5-10`, `docs/itinerary-analysis.md:27`, `app/Services/Itinerary/PlanningPolicy.php:98-104`, `app/Services/Itinerary/FixedSlotPlanner.php:130-131`. | Đếm từng bước; xác nhận payload thực tế không có exclusions/constraints còn sót. Không quy nguyên nhân cho “chưa chốt ngân sách” khi request thật là NULL. |
| 9 | **Groq/Geoapify mất key/quota/network.** | Thấp cho kết quả 201 rỗng này: có key ở mức boolean; lỗi provider bình thường trả lỗi; candidate rỗng có thể khiến Groq chưa được gọi. Không khẳng định credentials còn hợp lệ với provider vì không gọi network. `docs/itinerary-analysis.md:15`, `app/Services/Itinerary/GenerationService.php:25-28`, `app/Services/Itinerary/GroqPlannerClient.php:18-35`, `app/Services/Itinerary/FixedSlotPlanner.php:59`. | Kiểm tra xem có request outbound thực sự, response code an toàn; không log API key, Authorization, prompt hoặc raw AI reasoning. |

### 10.2. Các bước debug cụ thể để tự chạy

Các bước dưới đây là hướng dẫn; **không được thực hiện tự động trong phiên phân tích**. Để xem lại bằng chứng sẵn có, ưu tiên Network của request đã có và SELECT. Nút generate/save/PDF hoặc gọi TravelCatalog.pool không phải thao tác thuần đọc: generate tạo draft/replay, PDF có thể gọi save, Geo lookup có thể ghi cache/closure. Các điểm ghi được chứng minh ở `app/Http/Controllers/Api/V2/ItineraryPlannerController.php:85-87`, `public/vendor/aurel-ai/planner.js:400-401`, `app/Services/Itinerary/GeoapifyClient.php:46`, `app/Services/Itinerary/GeoapifyClient.php:91`, `app/Services/Itinerary/PlaceAvailability.php:63-77`. Nếu tái hiện có ghi, dùng môi trường/DB thử riêng; không seed/reset DB đang dùng.

**Bước 1 — đối chiếu đúng request của UI.** Mở DevTools → Network, xem request `planner/generate` đã có: URL, method, payload có đúng 5 field tại §2.1, budget có đúng JSON null, response days/items/warnings/readiness/status. Không copy Authorization/token ra báo cáo. Mở Elements: các câu Sáng/Trưa nằm dưới `#tripPlanningNotes` hay thật sự là `.trip-activity`; đối chiếu renderer tại `public/vendor/aurel-ai/planner.js:257-286`. Nếu asset đang tải khác bản repo, ghi lại URL và version query từ include tại `resources/views/user/itineraries.blade.php:20`, không vội kết luận lỗi backend khác.

**Bước 2 — query DB theo Location, không query cột city không tồn tại.** Chạy bằng DB client đang có kết nối, không nhúng credentials vào file/command/report. Các query sau dùng đúng schema ở `database/migrations/2026_05_01_152156_create_attraction_table.php:16-32`, `database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php:11-24`, `database/migrations/2026_10_31_000001_create_travel_source_places.php:10-17`:

```sql
SET TRANSACTION READ ONLY;
START TRANSACTION;

SELECT l.id, l.name, l.code, d.enabled
FROM location l
LEFT JOIN itinerary_destination d ON d.location_id = l.id
WHERE l.name = 'Đà Nẵng';

SELECT a.id, a.name, a.location_id, a.item_type, a.is_active,
       a.experience_category, a.slug, a.experience_location_code,
       a.experience_verified_at, a.experience_published_at,
       a.experience_archived_at, a.experience_duration_minutes,
       a.duration_hours, a.open_time, a.close_time, a.lat, a.lng,
       a.price_min, a.price_max
FROM attraction a
JOIN location l ON l.id = a.location_id
WHERE l.name = 'Đà Nẵng'
ORDER BY a.id;

-- Đếm theo đúng publication/category-type gates của publicQuery().
SELECT COUNT(*) AS public_poi_count
FROM attraction a
JOIN location l ON l.id = a.location_id
WHERE l.name = 'Đà Nẵng'
  AND a.is_active = 1 AND a.experience_archived_at IS NULL
  AND a.slug IS NOT NULL AND a.experience_verified_at IS NOT NULL
  AND a.experience_published_at <= NOW()
  AND l.code IS NOT NULL AND l.code = a.experience_location_code
  AND ((a.experience_category = 'tham-quan' AND a.item_type = 'attraction')
    OR (a.experience_category = 'am-thuc' AND a.item_type = 'restaurant')
    OR (a.experience_category IN ('vui-choi','dia-phuong') AND a.item_type = 'activity'));

SELECT p.id, p.external_id, p.name, p.category, p.is_enabled,
       p.hours_text, p.opening_schedule, p.visit_minutes,
       p.price_min, p.price_max, p.price_unit
FROM travel_source_place p
JOIN location l ON l.id = p.location_id
WHERE l.name = 'Đà Nẵng'
ORDER BY p.id;

SELECT DISTINCT l.name, l.code FROM location l ORDER BY l.name;
SELECT experience_category, item_type, COUNT(*) AS n
FROM attraction GROUP BY experience_category, item_type;
SELECT DISTINCT category FROM travel_source_place ORDER BY category;

ROLLBACK;
```

Kết quả tham chiếu khi phân tích: Location id3/code vn-da-nang, 11 attraction, hai public metadata hợp lệ, source Đà Nẵng0. Không tự chuyển location hay publish legacy để làm tăng count. `docs/itinerary-analysis.md:12`, `docs/itinerary-analysis.md:15`, `docs/itinerary-analysis.md:27`.

**Bước 3 — đặt breakpoint/logpoint để đếm funnel.** Trên một lượt tái hiện ở môi trường thử, ghi số lượng tại các vị trí sau; Xdebug logpoint/watch có thể thực hiện mà không sửa file. Nếu chọn thêm log tạm, đây là đề xuất cho người tự debug sau báo cáo, không phải thay đổi đã làm:

| Vị trí | Giá trị cần ghi | Mục đích |
| --- | --- | --- |
| `app/Http/Controllers/Api/V2/ItineraryPlannerController.php:81-84` | replay có/không, có đi vào prepare hay không; chỉ log boolean, không log request key/token | Phân biệt sinh mới với bản response cũ. |
| `app/Services/Itinerary/TravelCatalog.php:56-59` | region method, count trước/sau contains; không dump URL có query API key | Phân biệt geography với giờ. |
| `app/Services/Itinerary/TravelCatalog.php:62-73` | public_count, match tên0/1/nhiều, missing coordinates, accepted_public, added_geo | Nhìn catalogue và merge bị mất ở đâu. |
| `app/Services/Itinerary/TravelCatalog.php:77-87` | source matched/enabled, count nguồn giờ, count sau enrich/closure | Tách bổ sung giờ từ Sheet/JSON với provider raw. |
| `app/Services/Itinerary/FixedSlotPlanner.php:19-22` | after_availability, after_exclusions, after_unique, before_hours, after_hours | Chốt bước loại toàn bộ. |
| `app/Services/Itinerary/FixedSlotPlanner.php:43-58` | count eligible, candidates; histogram availability theo date/slot/category | Tách thiếu schedule với schedule có nhưng không dùng được. |
| `app/Services/Itinerary/DailySlotPolicy.php:68-95` | mã lý do wrong_category/no_schedule/schedule_issue/duration/window; status OpeningHours từng weekday | Xác minh provisional7day và duration. |
| `app/Services/Itinerary/FixedSlotPlanner.php:59-81` | candidates>0, có gọi Groq, provider status, số chunk | Chứng minh không phải Groq tự bịa placeholder. |
| `app/Services/Itinerary/FixedSlotPlanner.php:125-149` | chosen/accepted/skip_used/skip_start/skip_budget; visits mỗi ngày | Tách AI chọn rỗng với materialize loại sau AI. |
| `app/Services/Itinerary/ConversationalPlanner.php:95-104` | array_keys(part) và array_keys(prepared), có planning_metadata không | Chứng minh metadata rơi ở lớp ghép. |
| `app/Services/Itinerary/GenerationService.php:73` | incoming metadata có/không, tổng activities | Đối chiếu lưu/mở lại. |

Ví dụ thống kê có thể dùng trong debugger tại trước/sau dòng22; chỉ có số đếm và tên field, không có credentials hoặc nội dung prompt. Các field và mốc log lấy từ `app/Services/Itinerary/FixedSlotPlanner.php:19-24`, `app/Services/Itinerary/DailySlotPolicy.php:39-47`:

```php
[
    'poi_count' => count($places),
    'has_schedule' => count(array_filter($places, fn ($p) => !empty($p['opening_schedule']))),
    'has_provider_hours' => count(array_filter($places, fn ($p) => !empty($p['opening_hours']))),
    'has_regular_opening' => count(array_filter($places, fn ($p) => !empty($p['regular_opening']))),
    'has_hours_text' => count(array_filter($places, fn ($p) => !empty($p['hours_text']))),
]
```

Kỳ vọng theo snapshot đã đọc: raw provider cache20; toàn bộ bốn chỉ số giờ0; sau policy schedule filter0; candidates0; Groq planning call bị bỏ; accepted0 cho hai ngày; response201 có11 warnings. Không khẳng định số lượng chính xác ở boundary/public merge chưa đo. `docs/itinerary-analysis.md:18`, `docs/itinerary-analysis.md:21`, `docs/itinerary-analysis.md:30`, `app/Services/Itinerary/FixedSlotPlanner.php:21-22`, `app/Services/Itinerary/FixedSlotPlanner.php:59-90`.

**Bước 4 — kiểm chứng chính sách giờ khi pool đã có schedule có nguồn.** So cùng POI ở hai cấu hình: chưa chốt ngày (`_provisional=true, _hours_date_confirmed=false`) và có ngày ghé thật (`_hours_date_confirmed=true`); theo dõi 7 status và toàn khoảng start→end. Không tự điền giờ giả chỉ để vượt filter. Chốt POI bị loại vì giờ nguồn thiếu, ngày đóng cửa, thời lượng dài hay điều kiện AND7 ngày. `app/Services/Itinerary/DailySlotPolicy.php:80-95`, `app/Services/Places/OpeningHours.php:122-131`.

**Bước 5 — tách budget khỏi nguyên nhân giờ.** Trên môi trường thử, so NULL budget với budget cụ thể trên cùng pool có schedule; theo dõi quote NULL/known và spent/cap. Với NULL, cap phải NULL và không có skip_budget. Kiểm tra giá 0 có ticket free với nguồn khác giá NULL; đừng lấy price_min=0 legacy làm vé miễn phí. `app/Services/Itinerary/FixedSlotPlanner.php:129-132`, `app/Services/Itinerary/VisitInformation.php:30-32`, `app/Services/Itinerary/TravelCatalog.php:32`.

**Bước 6 — đọc kết quả đã lưu của riêng lịch cần kiểm tra.** Với itinerary ID đang mở (không đoán ID từ R4), chạy SELECT trong transaction READ ONLY rồi ROLLBACK: đếm itinerary_item theo ngày, xem status/revision/planning_metadata; xem riêng response_status và số warnings/items của replay. Cấu trúc lưu tại `app/Services/Itinerary/IdempotentWriter.php:34-37`, `app/Http/Resources/ItineraryTripResource.php:34-36`. Không lấy toàn bộ bảng replay vì có thể chứa nội dung riêng của các hội thoại khác.

```sql
-- Thay <ITINERARY_ID> bằng ID từ response của chính lịch đang kiểm tra.
SET TRANSACTION READ ONLY;
START TRANSACTION;
SELECT status, revision, planning_metadata, transport, pace,
       activity_window_confirmed
FROM itinerary WHERE id = <ITINERARY_ID>;
SELECT day_number, COUNT(*) AS activities
FROM itinerary_item WHERE itinerary_id = <ITINERARY_ID>
GROUP BY day_number;
SELECT operation, response_status,
       JSON_LENGTH(response_body, '$.planning_warnings') AS warnings,
       JSON_LENGTH(response_body, '$.data.days[0].items') AS day1_items,
       JSON_LENGTH(response_body, '$.data.days[1].items') AS day2_items
FROM itinerary_write_request
WHERE itinerary_id = <ITINERARY_ID> AND operation = 'planner:generate';
ROLLBACK;
```

**Bước 7 — kiểm tra lưu/Undo/mở lại và contract hiện tại trên DB thử riêng.** Tạo lịch có POI hợp lệ; xem status draft và nhãn UI trước Save; Save→status saved/revision tăng; sửa→Undo chỉ đổi frontend; Save sau Undo→mở lại; so warnings/slots và PDF. Đối chiếu `part.planning_metadata` trước ghép và dữ liệu NULL sau ghép, đồng thời kiểm tra response body cũ có thể replay khi dùng cùng key. Các mốc code: `public/vendor/aurel-ai/planner.js:140-147`, `public/vendor/aurel-ai/planner.js:264`, `public/vendor/aurel-ai/planner.js:383-402`, `app/Services/Itinerary/PlannerService.php:78-84`, `app/Services/Itinerary/ConversationalPlanner.php:94-99`.

**Bước 8 — nếu sau này sửa code, cập nhật checklist hồi quy trước khi tuyên bố hoàn tất.** Cần bao phủ lịch nguồn thiếu giờ/0 POI, budget NULL, food/visit đúng slot, normal3–4, provisional so với ngày thật, partial day, nhiều ngày không trùng, cảnh báo lưu/mở lại, Save rỗng theo quyết định sản phẩm, Undo và lỗi/quota thật; đồng thời cập nhật fixture từ format cũ sang days object/5 slots. Đây là danh sách đề xuất, không phải quyết định thay đổi yêu cầu hay kết quả test. Bằng chứng phạm vi cần giữ: `docs/itinerary-approved-requirements.md:9-17`, `tests/Feature/ItineraryPlannerTest.php:114-136`, `app/Services/Itinerary/GroqItineraryContract.php:8-20`. Không có code/data nào được sửa để thực hiện các bước này trong báo cáo.
