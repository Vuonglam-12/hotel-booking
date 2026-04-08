<div class="prose max-w-3xl mx-auto px-4 py-8">

    <!-- TIÊU ĐỀ CHÍNH -->
    <h1 class="text-3xl md:text-4xl font-bold text-center mb-6 text-gray-800 leading-tight">
        🌊 Top View Resort Pool: Những hồ bơi vô cực đẹp nhất 2026
    </h1>

    <!-- MỞ ĐẦU -->
    <div class="text-lg text-gray-700 mb-8 text-center leading-relaxed">
        Nói thật là mấy năm gần đây, đi nghỉ dưỡng mà resort không có hồ bơi vô cực thì tự nhiên thấy thiếu thiếu đúng không?<br><br>
        Sang năm 2026, tiêu chuẩn của dân xê dịch không còn dừng ở hồ bơi tràn bờ đơn thuần nữa. 
        <strong class="text-orange-600">Quan trọng nhất là cái “view” đằng trước nó!</strong><br><br>
        Ngâm mình trong làn nước mát rượi, tay cầm ly cocktail, mắt nhìn ra biển hay núi non mới thực sự là <strong class="text-orange-600">“chữa lành” xịn sò</strong>.
    </div>

    <!-- ==================== AUDIO PLAYER ==================== -->
<div class="audio-section bg-gradient-to-r from-amber-50 to-white border border-amber-200 rounded-3xl p-6 mb-12 shadow-lg">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <span class="text-4xl">
                    <svg class="w-10 h-10 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>
                    </svg>
                </span>
                <div>
                    <h3 class="text-xl font-bold text-gray-800">Lười đọc? Nghe ngay nhé!</h3>
                    <p class="text-sm text-gray-500">• Khoảng 7 phút • Giọng đọc tự động</p>
                </div>
            </div>
            <div id="audioStatus" class="text-xs bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full font-medium">Sẵn sàng nghe</div>
        </div>

        <div class="flex gap-3 mt-4 justify-center">
            <button id="playPauseBtn" onclick="toggleAudio()" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded-full font-semibold transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                <span id="playPauseText">Phát giọng đọc</span>
            </button>
            <button id="resetBtn" onclick="restartAudio()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-full font-semibold transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Nghe lại từ đầu
            </button>
        </div>

        <div class="speed-controls mt-4 flex justify-center gap-2">
            <button class="speed-btn px-4 py-1.5 text-sm rounded-full bg-gray-100 hover:bg-gray-200 transition" onclick="changeSpeed(0.75, this)">0.75x</button>
            <button class="speed-btn px-4 py-1.5 text-sm rounded-full bg-gray-100 hover:bg-gray-200 transition active-speed" style="background:#f97316; color:white;" onclick="changeSpeed(1, this)">1x</button>
            <button class="speed-btn px-4 py-1.5 text-sm rounded-full bg-gray-100 hover:bg-gray-200 transition" onclick="changeSpeed(1.25, this)">1.25x</button>
            <button class="speed-btn px-4 py-1.5 text-sm rounded-full bg-gray-100 hover:bg-gray-200 transition" onclick="changeSpeed(1.5, this)">1.5x</button>
        </div>
    </div>

    <!-- ==================== DANH SÁCH RESORT ==================== -->
    <div class="space-y-12 mt-8">

        <!-- Resort 1 -->
        <div class="resort-card bg-white rounded-2xl p-6 shadow-md border-l-8 border-l-orange-400">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-orange-500 text-white rounded-full flex items-center justify-center font-bold text-xl">1</div>
                <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
                    <div class="relative">
                        <svg class="w-8 h-8 text-[#F59E0B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <svg class="w-5 h-5 text-[#87CEFA] absolute -bottom-1 -right-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M6 14h2m6 0h2M7 10v6a3 3 0 003 3h4a3 3 0 003-3v-6"></path>
                        </svg>
                    </div>
                    <span>Bể bơi <span class="bg-gradient-to-r from-[#87CEFA] to-[#F59E0B] bg-clip-text text-transparent">“giao thoa đất trời”</span> – Bán đảo Sơn Trà, Đà Nẵng</span>
                </h2>
            </div>
            <p class="text-gray-700 leading-relaxed">
                Nếu bạn nhìn bức ảnh cover đầu bài viết, đó chính là góc mình chụp từ một khu resort trên bán đảo Sơn Trà. 
                Ở đây, bạn sẽ hiểu rõ thế nào là <strong class="text-orange-600">“giao thoa đất trời”</strong> – ranh giới mờ ảo giữa nước hồ bơi, mặt biển và bầu trời. 
                Sàn gỗ mộc mạc chạy dọc theo bờ hồ tạo cảm giác cực kỳ gần gũi với thiên nhiên.
            </p>
            <p class="text-gray-700 leading-relaxed mt-3">
                Buổi sáng dậy sớm tầm 5h30 ra đây bơi, ngắm bình minh từ từ ló dạng sau dãy núi đằng xa phải nói là <strong class="text-orange-600">“nổi da gà”</strong> vì đẹp. 
                Gió biển mát rượi, nước hồ trong vắt. Giá phòng dao động 4 - 8 triệu/đêm. Từ sân bay Đà Nẵng chỉ 30-40 phút xe.
            </p>
        </div>

        <!-- Resort 2 -->
        <div class="resort-card bg-white rounded-2xl p-6 shadow-md border-l-8 border-l-orange-400">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-orange-500 text-white rounded-full flex items-center justify-center font-bold text-xl">2</div>
                <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <svg class="w-8 h-8 animate-pulse" fill="none" stroke="url(#cloudGradient)" viewBox="0 0 24 24">
                        <defs>
                            <linearGradient id="cloudGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#E8F4FD;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#87CEFA;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"></path>
                    </svg>
                    <span>Hồ bơi <span class="bg-gradient-to-r from-[#C9D3DD] to-[#87CEFA] bg-clip-text text-transparent">“bơi trên mây”</span> – Thung lũng Mường Hoa, Sapa</span>
                </h2>
            </div>
            <p class="text-gray-700 leading-relaxed">
                Nhắc đến hồ bơi vô cực thì không thể bỏ qua đặc sản <strong class="text-orange-600">“bơi trên mây”</strong> ở vùng Tây Bắc. 
                Nổi bật nhất là hồ bơi nước nóng của Topas Ecolodge. Cảm giác bơi giữa trời đông se lạnh, nước ấm rực bốc khói nghi ngút, 
                bao quanh là thung lũng Mường Hoa sâu thẳm cùng ruộng bậc thang.
            </p>
            <p class="text-gray-700 leading-relaxed mt-3">
                Nếu đi đúng mùa lúa chín (tháng 9, tháng 10), khung cảnh vàng rực như tranh. Đường hơi dốc, say xe nên uống thuốc trước. 
                Giá khoảng 6 - 9 triệu/đêm, rất hay cháy phòng cuối tuần, phải đặt trước 1-2 tháng.
            </p>
        </div>

        <!-- Resort 3 -->
        <div class="resort-card bg-white rounded-2xl p-6 shadow-md border-l-8 border-l-orange-400">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-orange-500 text-white rounded-full flex items-center justify-center font-bold text-xl">3</div>
                <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <svg class="w-8 h-8" fill="none" stroke="url(#sunsetGradient)" viewBox="0 0 24 24">
                        <defs>
                            <linearGradient id="sunsetGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#F59E0B;stop-opacity:1" />
                                <stop offset="50%" style="stop-color:#EF4444;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#F59E0B;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 20h14"></path>
                    </svg>
                    <span>Đón hoàng hôn <span class="bg-gradient-to-r from-[#F59E0B] via-[#EF4444] to-[#F59E0B] bg-clip-text text-transparent animate-pulse">“triệu đô”</span> – Bãi Trường, Phú Quốc</span>
                </h2>
            </div>
            <p class="text-gray-700 leading-relaxed">
                Đi Phú Quốc mà không ngâm mình ở hồ bơi vô cực ngắm mặt trời lặn thì phí mất nửa chuyến đi. 
                Ở mạn Bãi Trường, mình ưng nhất các khu hồ bơi tone màu tối (như Regent). Màu gạch tối ở đáy hồ làm nổi bật sắc cam đỏ rực rỡ của bầu trời lúc hoàng hôn – đúng chất <strong class="text-orange-600">“triệu đô”</strong>.
            </p>
            <p class="text-gray-700 leading-relaxed mt-3">
                Không gian tĩnh lặng, không ồn ào nhạc xập xình. Tầm 5h chiều bơi xong gọi đồ nhâm nhi và ly mocktail là chuẩn bài. 
                Giá resort 5 sao từ 5 - 10 triệu/đêm. Từ sân bay Phú Quốc chỉ 15 phút taxi.
            </p>
        </div>

        <!-- Resort 4 -->
        <div class="resort-card bg-white rounded-2xl p-6 shadow-md border-l-8 border-l-orange-400">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-orange-500 text-white rounded-full flex items-center justify-center font-bold text-xl">4</div>
                <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #2D6A4F;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2 20l4-4 4 4 4-4 4 4 4-4M2 12l4-4 4 4 4-4 4 4 4-4"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 4l4 4-4 4-4-4 4-4z"></path>
                    </svg>
                    <span>Ẩn mình giữa vách đá – <span style="background: linear-gradient(135deg, #2D6A4F, #52B788, #1B4332); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Vịnh Vĩnh Hy, Ninh Thuận</span></span>
                </h2>
            </div>
            <p class="text-gray-700 leading-relaxed">
                Dành riêng cho ai thích sự riêng tư tuyệt đối và muốn trốn hẳn khỏi phố thị. Khu vực Vĩnh Hy có những resort (tiêu biểu như Amanoi) 
                thiết kế hồ bơi vô cực chìa thẳng ra vách núi đá vôi, bên dưới là vịnh biển xanh ngắt nguyên sơ. 
                Cái tĩnh lặng ở đây khiến mình chỉ muốn nằm dài trên ghế tắm nắng, đọc vài trang sách rồi ngủ gật.
            </p>
            <p class="text-gray-700 leading-relaxed mt-3">
                Nước hồ bơi xử lý muối cực tốt, bơi xong da không bị rít. Giá thuộc hàng “thượng lưu” từ 15 - 30 triệu/đêm. 
                Mẹo nhỏ: rủ nhóm bạn 4-6 người đi chung, share tiền thuê villa lớn có private pool sẽ hời hơn.
            </p>
        </div>
    </div>

    <!-- TIPS BOX -->
    <div class="tips-box bg-emerald-50 border-l-8 border-emerald-500 rounded-2xl p-6 my-12">
        <h3 class="text-2xl font-bold text-emerald-800 mb-4 flex items-center gap-2">💡 Vài lưu ý nhỏ (nhưng có võ)</h3>
            <ul class="space-y-3">
                <li class="flex items-start gap-3 p-3 rounded-lg border-l-4 border-[#F59E0B] bg-white/50">
                    <svg class="w-5 h-5 text-[#F59E0B] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <strong class="text-[#3A4A5A]">Thời điểm vàng:</strong> 
                        <span class="text-gray-600">Sáng sớm (6h-7h30) hoặc chiều tà (16h30-18h). Tránh giữa trưa nắng gắt.</span>
                    </div>
                </li>
                <li class="flex items-start gap-3 p-3 rounded-lg border-l-4 border-[#EF4444] bg-white/50">
                    <svg class="w-5 h-5 text-[#EF4444] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <div>
                        <strong class="text-[#3A4A5A]">Trang phục:</strong> 
                        <span class="text-gray-600">Mặc đồ bơi màu nổi (đỏ, cam, trắng, vàng neon) để nổi bật giữa nền xanh.</span>
                    </div>
                </li>
                <li class="flex items-start gap-3 p-3 rounded-lg border-l-4 border-[#52B788] bg-white/50">
                    <svg class="w-5 h-5 text-[#52B788] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M6 14h2m6 0h2M7 10v6a3 3 0 003 3h4a3 3 0 003-3v-6"></path>
                    </svg>
                    <div>
                        <strong class="text-[#3A4A5A]">Bảo vệ da & môi trường:</strong> 
                        <span class="text-gray-600">Dùng kem chống nắng water-proof, ưu tiên loại reef-safe.</span>
                    </div>
                </li>
                <li class="flex items-start gap-3 p-3 rounded-lg border-l-4 border-[#87CEFA] bg-white/50">
                    <svg class="w-5 h-5 text-[#87CEFA] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <div>
                        <strong class="text-[#3A4A5A]">Mẹo săn phòng:</strong> 
                        <span class="text-gray-600">Theo dõi giá trên booking, canh flash sale để có combo giá mềm.</span>
                    </div>
                </li>
            </ul>
    </div>

    <!-- BẢNG SO SÁNH NHANH -->
    <div class="compare-table bg-white rounded-2xl overflow-hidden shadow-md my-12">
        <table class="w-full text-sm">
            <thead class="bg-emerald-700 text-white">
                <tr><th class="p-3 text-left">Resort</th><th class="p-3 text-left">Giá tham khảo</th><th class="p-3 text-left">Điểm nhấn</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <tr class="hover:bg-gray-50"><td class="p-3 font-medium">Sơn Trà (Đà Nẵng)</td><td class="p-3">4 - 8 tr/đêm</td><td class="p-3">Giao thoa đất trời, bình minh đẹp</td></tr>
                <tr class="hover:bg-gray-50"><td class="p-3 font-medium">Mường Hoa (Sapa)</td><td class="p-3">6 - 9 tr/đêm</td><td class="p-3">Bơi trên mây, nước nóng giữa trời lạnh</td></tr>
                <tr class="hover:bg-gray-50"><td class="p-3 font-medium">Bãi Trường (Phú Quốc)</td><td class="p-3">5 - 10 tr/đêm</td><td class="p-3">Hoàng hôn triệu đô, tone tối sang trọng</td></tr>
                <tr class="hover:bg-gray-50"><td class="p-3 font-medium">Vịnh Vĩnh Hy (Ninh Thuận)</td><td class="p-3">15 - 30 tr/đêm</td><td class="p-3">Riêng tư tuyệt đối, vách đá hùng vĩ</td></tr>
            </tbody>
        </table>
    </div>

    <!-- KẾT BÀI + CTA -->
    <div class="text-center my-16">
        <p class="text-xl text-gray-700 mb-6 leading-relaxed">
            Hồ bơi vô cực thực sự không chỉ là chỗ để bơi. Nó như một cái rạp hát ngoài trời,<br>
            nơi bạn ngồi hàng ghế VIP để xem mẹ thiên nhiên trình diễn.
        </p>
        <p class="text-lg text-gray-600 mb-6">
            Thà nhịn ăn nhịn tiêu lặt vặt vài tháng, dồn tiền để chốt một đêm ở resort có view hồ bơi thật xịn còn hơn đi dàn trải.<br>
            <strong class="text-orange-600">Sang năm 2026 rồi, tự thưởng cho bản thân một chuyến đi đàng hoàng thôi!</strong>
        </p>
        <button onclick="document.getElementById('commentSection').scrollIntoView({ behavior: 'smooth' })" 
            class="relative overflow-hidden bg-gradient-to-r from-[#F59E0B] to-[#EF4444] hover:from-[#EF4444] hover:to-[#F59E0B] text-white px-8 py-3 rounded-full font-semibold text-lg shadow-md transition-all duration-300 hover:scale-105 flex items-center gap-2 mx-auto group">
            <svg class="w-5 h-5 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
            Bạn thích hồ bơi nào nhất? Comment ngay!
            <span class="absolute inset-0 rounded-full bg-white opacity-0 group-hover:opacity-20 transition-opacity duration-300"></span>
        </button>
    </div>

    <!-- KHUNG COMMENT -->
    <div id="commentSection" class="bg-white rounded-2xl p-6 mt-8 shadow-lg border border-[#C9D3DD]/30">
        <h3 class="text-2xl font-bold text-[#3A4A5A] mb-4 flex items-center gap-2">
            <svg class="w-7 h-7 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
            Bình luận & chia sẻ
        </h3>
        
        <div id="commentsList" class="space-y-4 mb-6 max-h-96 overflow-y-auto">
            <!-- Comment mẫu 1 -->
            <div class="bg-[#EAF3FF] p-4 rounded-xl shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-8 h-8 bg-gradient-to-r from-[#F59E0B] to-[#EF4444] rounded-full flex items-center justify-center text-white text-sm font-bold">M</div>
                    <span class="font-semibold text-[#3A4A5A]">Minh Nguyễn</span>
                    <span class="text-xs text-[#C9D3DD]">2 ngày trước</span>
                </div>
                <p class="text-[#5A6A7A] ml-2">Mình đã từng trải nghiệm hồ bơi ở Sơn Trà, đúng là "giao thoa đất trời" thật sự. Sáng sớm bơi mà thấy lâng lâng!</p>
                <div class="flex gap-3 mt-2 ml-2">
                    <button class="text-xs text-[#C9D3DD] hover:text-[#87CEFA] transition flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path>
                        </svg>
                        Thích
                    </button>
                    <button class="text-xs text-[#C9D3DD] hover:text-[#87CEFA] transition flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Trả lời
                    </button>
                </div>
            </div>
            
            <!-- Comment mẫu 2 -->
            <div class="bg-[#EAF3FF] p-4 rounded-xl shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-8 h-8 bg-gradient-to-r from-[#52B788] to-[#2D6A4F] rounded-full flex items-center justify-center text-white text-sm font-bold">L</div>
                    <span class="font-semibold text-[#3A4A5A]">Lan Hương</span>
                    <span class="text-xs text-[#C9D3DD]">5 ngày trước</span>
                </div>
                <p class="text-[#5A6A7A] ml-2">Topas Ecolodge đúng là "bơi trên mây" có một không hai. Mùa lúa chín đẹp như phim Hàn.</p>
                <div class="flex gap-3 mt-2 ml-2">
                    <button class="text-xs text-[#C9D3DD] hover:text-[#87CEFA] transition flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path>
                        </svg>
                        Thích
                    </button>
                    <button class="text-xs text-[#C9D3DD] hover:text-[#87CEFA] transition flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Trả lời
                    </button>
                </div>
            </div>
        </div>
        
        <div class="border-t border-[#C9D3DD]/30 pt-4">
            <textarea id="commentInput" rows="3" class="w-full border border-[#C9D3DD] rounded-xl p-3 focus:outline-none focus:ring-2 focus:ring-[#87CEFA] transition" placeholder="Viết bình luận của bạn..."></textarea>
            <div class="flex justify-end mt-3">
                <button onclick="addComment()" class="bg-gradient-to-r from-[#87CEFA] to-[#7BC4F5] hover:from-[#7BC4F5] hover:to-[#87CEFA] text-[#3A4A5A] px-6 py-2 rounded-full font-semibold transition-all duration-300 hover:scale-105 flex items-center gap-2 shadow-md">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    Gửi bình luận
                </button>
            </div>
        </div>
    </div>
</div>

<!-- STYLES bổ sung -->
<style>
    .prose {
        max-width: 64rem;
        margin-left: auto;
        margin-right: auto;
    }
    .speed-btn.active-speed {
        background-color: #f97316 !important;
        color: white !important;
    }
    .resort-card {
        transition: all 0.2s ease;
    }
    .resort-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 30px -12px rgba(0,0,0,0.1);
    }
</style>

<!-- SCRIPT: Audio Player hoàn chỉnh với TTS -->
<script>
    // ========== DATA ==========
    const scriptText = `
Chào bạn hôm này mình kể cho bạn nghe về Top View Resort Pool: Những hồ bơi vô cực đẹp nhất 2026
Nói thật là mấy năm gần đây, đi nghỉ dưỡng mà resort không có hồ bơi vô cực thì tự nhiên thấy thiếu thiếu đúng không,
Sang năm 2026 rồi, tiêu chuẩn của dân xê dịch đâu chỉ dừng ở một cái hồ bơi tràn bờ đơn thuần nữa. Quan trọng là cái "view" đằng trước nó kìa
Ngâm mình trong làn nước mát rượi, tay cầm ly cocktail, mắt nhìn ra biển hay núi non mới thực sự là “chữa lành” xịn sò.

Đầu tiên, bể bơi "giao thoa đất trời" ở bán đảo Sơn Trà, Đà Nẵng.
Nếu bạn nhìn bức ảnh cover đầu bài viết, đó chính là góc mình chụp từ một khu resort trên bán đảo Sơn Trà. Ở đây, bạn sẽ hiểu rõ thế nào là “giao thoa đất trời” – ranh giới mờ ảo giữa nước hồ bơi, mặt biển và bầu trời. Sàn gỗ mộc mạc chạy dọc theo bờ hồ tạo cảm giác cực kỳ gần gũi với thiên nhiên.
Buổi sáng dậy sớm tầm 5h30 ra đây bơi, ngắm bình minh từ từ ló dạng sau dãy núi đằng xa phải nói là “nổi da gà” vì đẹp. Gió biển mát rượi, nước hồ trong vắt. Giá phòng dao động 4 - 8 triệu/đêm. Từ sân bay Đà Nẵng chỉ 30-40 phút xe.

Thứ hai, hồ bơi "bơi trên mây" ở thung lũng Mường Hoa, Sapa.
Nhắc đến hồ bơi vô cực thì không thể bỏ qua đặc sản “bơi trên mây” ở vùng Tây Bắc. Nổi bật nhất là hồ bơi nước nóng của Topas Ecolodge. Cảm giác bơi giữa trời đông se lạnh, nước ấm rực bốc khói nghi ngút, bao quanh là thung lũng Mường Hoa sâu thẳm cùng ruộng bậc thang.
Nếu đi đúng mùa lúa chín (tháng 9, tháng 10), khung cảnh vàng rực như tranh. Đường hơi dốc, say xe nên uống thuốc trước. Giá khoảng 6 - 9 triệu/đêm, rất hay cháy phòng cuối tuần, phải đặt trước 1-2 tháng.

Thứ ba, đón hoàng hôn "triệu đô" tại Bãi Trường, Phú Quốc.
Đi Phú Quốc mà không ngâm mình ở hồ bơi vô cực ngắm mặt trời lặn thì phí mất nửa chuyến đi. Ở mạn Bãi Trường, mình ưng nhất các khu hồ bơi tone màu tối (như Regent). Màu gạch tối ở đáy hồ làm nổi bật sắc cam đỏ rực rỡ của bầu trời lúc hoàng hôn – đúng chất “triệu đô”.
Không gian tĩnh lặng, không ồn ào nhạc xập xình. Tầm 5h chiều bơi xong gọi đồ nhâm nhi và ly mocktail là chuẩn bài. Giá resort 5 sao từ 5 - 10 triệu/đêm. Từ sân bay Phú Quốc chỉ 15 phút taxi.

Thứ tư, ẩn mình giữa vách đá Vịnh Vĩnh Hy, Ninh Thuận.
Dành riêng cho ai thích sự riêng tư tuyệt đối và muốn trốn hẳn khỏi phố thị. Khu vực Vĩnh Hy có những resort (tiêu biểu như Amanoi) thiết kế hồ bơi vô cực chìa thẳng ra vách núi đá vôi, bên dưới là vịnh biển xanh ngắt nguyên sơ. Cái tĩnh lặng ở đây khiến mình chỉ muốn nằm dài trên ghế tắm nắng, đọc vài trang sách rồi ngủ gật.
Nước hồ bơi xử lý muối cực tốt, bơi xong da không bị rít. Giá thuộc hàng “bình dân” từ 800 - 1 triệu/đêm. Mẹo nhỏ: rủ nhóm bạn 4-6 người đi chung, share tiền thuê villa lớn có private pool sẽ hời hơn.

Vài lưu ý nhỏ (nhưng có võ)
Thời điểm vàng: Sáng sớm (6h-7h30) hoặc chiều tà (16h30-18h). Tránh giữa trưa nắng gắt.
Trang phục: Mặc đồ bơi màu nổi (đỏ, cam, trắng, vàng neon) để nổi bật giữa nền xanh.
Bảo vệ da & môi trường: Dùng kem chống nắng water-proof, ưu tiên loại reef-safe.
Mẹo săn phòng: Theo dõi giá trên booking, canh flash sale để có combo giá mềm.

Resort Sơn Trà (Đà Nẵng) Giá tham khảo 4 - 8 tr/đêm Giao thoa đất trời, bình minh đẹp
Resort Mường Hoa (Sapa) Giá tham khảo 6 - 9 tr/đêm Bơi trên mây, nước nóng giữa trời lạnh
Resort Bãi Trường (Phú Quốc) Giá tham khảo 5 - 10 tr/đêm Hoàng hôn triệu đô, tone tối sang trọng
Resort Vịnh Vĩnh Hy (Ninh Thuận) Giá tham khảo 15 - 30 tr/đêm Riêng tư tuyệt đối, vách đá hùng vĩ

Chốt lại, hồ bơi vô cực không chỉ là chỗ để bơi. Nó như một rạp hát ngoài trời, nơi bạn ngồi hàng ghế VIP ngắm thiên nhiên trình diễn.
Thà nhịn ăn nhịn tiêu lặt vặt vài tháng, dồn tiền để chốt một đêm ở resort có view hồ bơi thật xịn còn hơn đi dàn trải.
Sang năm 2026 rồi, tự thưởng cho bản thân một chuyến đi đàng hoàng thôi!
    `.trim();

    // ========== AUDIO LOGIC ==========
    const synth = window.speechSynthesis;
    let utterance = null;
    let isPaused = false;
    let currentSpeed = 1.0;

    const btnIcon = document.querySelector('#playPauseBtn svg');
    const btnText = document.getElementById('playPauseText');
    const btnPlay = document.getElementById('playPauseBtn');
    const statusEl = document.getElementById('audioStatus');

    function updateUI(state) {
        if (state === 'playing') {
            btnIcon.innerHTML = `<path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>`;
            btnText.textContent = 'Tạm dừng';
            btnPlay.className = 'bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-full font-semibold transition flex items-center gap-2';
            statusEl.textContent = 'Đang phát...';
            statusEl.className = 'text-xs bg-green-100 text-green-700 px-3 py-1 rounded-full font-medium';
        } else if (state === 'paused') {
            btnIcon.innerHTML = `<path d="M8 5v14l11-7z"/>`;
            btnText.textContent = 'Tiếp tục';
            btnPlay.className = 'bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-full font-semibold transition flex items-center gap-2';
            statusEl.textContent = 'Đã tạm dừng';
            statusEl.className = 'text-xs bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full font-medium';
        } else {
            btnIcon.innerHTML = `<path d="M8 5v14l11-7z"/>`;
            btnText.textContent = 'Phát giọng đọc';
            btnPlay.className = 'bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded-full font-semibold transition flex items-center gap-2';
            statusEl.textContent = 'Sẵn sàng nghe';
            statusEl.className = 'text-xs bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full font-medium';
        }
    }

    function toggleAudio() {
        if (synth.speaking) {
            if (isPaused) {
                synth.resume();
                isPaused = false;
                updateUI('playing');
            } else {
                synth.pause();
                isPaused = true;
                updateUI('paused');
            }
        } else {
            // Bắt đầu đọc mới
            utterance = new SpeechSynthesisUtterance(scriptText);
            utterance.lang = 'vi-VN';
            utterance.rate = currentSpeed;
            
            // Tìm giọng Nam hoặc tiếng Việt tốt nhất
            const voices = synth.getVoices();
            const vietVoice = voices.find(v => v.lang.includes('vi') && v.name.includes('Nam')) || voices.find(v => v.lang.includes('vi'));
            if (vietVoice) utterance.voice = vietVoice;

            utterance.onend = () => {
                isPaused = false;
                updateUI('stopped');
            };

            synth.speak(utterance);
            isPaused = false;
            updateUI('playing');
        }
    }

    function restartAudio() {
        synth.cancel();
        isPaused = false;
        updateUI('stopped');
        setTimeout(() => { toggleAudio(); }, 200); // Tự động đọc lại luôn cho mượt
    }

    function changeSpeed(rate, btn) {
        currentSpeed = rate;
        
        // Reset UI nút tốc độ
        document.querySelectorAll('.speed-btn').forEach(b => {
            b.style.background = '#f3f4f6'; 
            b.style.color = 'black';
        });
        btn.style.background = '#f97316';
        btn.style.color = 'white';

        // Nếu đang đọc thì phải cancel và đọc lại (Web Speech API bắt buộc)
        if (synth.speaking && !isPaused) {
            restartAudio();
        }
    }

    // ========== COMMENT LOGIC (Có check Đăng Nhập) ==========
    function addComment() {
        // 1. Kiểm tra localStorage
        const userStr = localStorage.getItem('user');
        if (!userStr) {
            alert('Vui lòng đăng nhập để có thể gửi bình luận nhé!');
            // window.location.href = '/login'; // Mở dòng này nếu muốn ép đá về trang login
            return;
        }

        const user = JSON.parse(userStr);
        const input = document.getElementById('commentInput');
        const commentsList = document.getElementById('commentsList');
        
        if (input.value.trim() === '') return;
        
        const newComment = document.createElement('div');
        newComment.className = 'bg-[#EAF3FF] p-4 rounded-xl shadow-sm hover:shadow-md transition-all mt-4';
        
        const firstLetter = user.name ? user.name.charAt(0).toUpperCase() : 'U';

        newComment.innerHTML = `
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 bg-gradient-to-r from-[#87CEFA] to-[#3A4A5A] rounded-full flex items-center justify-center text-white text-sm font-bold">
                    ${firstLetter}
                </div>
                <span class="font-semibold text-[#3A4A5A]">${user.name}</span>
                <span class="text-xs text-[#C9D3DD]">Vừa xong</span>
            </div>
            <p class="text-[#5A6A7A] ml-2">${input.value.replace(/</g, '&lt;')}</p>
        `;
        
        commentsList.prepend(newComment);
        input.value = '';
    }

    // Fix lỗi tải giọng trên một số trình duyệt
    window.speechSynthesis.onvoiceschanged = () => { window.speechSynthesis.getVoices(); };

    // ========== SPEED BUTTONS EVENT LISTENERS ==========
    document.querySelectorAll('.speed-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const rate = parseFloat(this.dataset.speed);
            changeSpeed(rate, this);
        });
    });
</script>