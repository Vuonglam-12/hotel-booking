# TODO: Improve Home.blade.php UI
- [ ] Step 1: Create optimized Home.blade.php with fixed flash banner, cleaned CSS, better responsive
- [ ] Step 2: Test responsive, functionality (search, modal, chat, wishlist)
- [ ] Step 3: npm run dev && refresh browser
- [x] Xác định controllers, routes, models, views
- [x] Phát hiện lỗi chính: duplicate migration timestamps + BookingCtroller typo

## 🔧 Fix blockers detected
### 1. MySQL connection refused (Laragon?)
### 2. Duplicate migrations (30+ files 2026_04_02_013436_*)
### 3. BookingCtroller.php typo

**Next steps:**
- [ ] Start Laragon MySQL
- [ ] Create .env with DB config  
- [ ] Rename duplicate migrations unique timestamps
- [ ] Delete app/Models/BookingCtroller.php
- [ ] php artisan key:generate
- [ ] php artisan migrate
- [ ] npm install && npm run dev
- [ ] php artisan serve → http://localhost:8000

## 🔧 Potential improvements
- Add seeders/data
- Frontend polish (missing views like booking.blade.php?)
- API docs (Postman/Swagger)
- Tests
