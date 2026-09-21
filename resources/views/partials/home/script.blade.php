<script>
    // ========================
    // LOAD MORE FUNCTIONALITY
    // ========================
    (function() {
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        if (!loadMoreBtn) return;

        const hotelGrid = document.getElementById('allHotelsGrid');
        const loadMoreContainer = document.getElementById('loadMoreContainer');
        const shownCountSpan = document.getElementById('shownCount');
        const totalCountSpan = document.getElementById('totalCount');
        
        let isLoading = false;
        let currentPage = parseInt(loadMoreBtn.dataset.nextPage);
        const lastPage = parseInt(loadMoreBtn.dataset.lastPage);
        const totalHotels = parseInt(loadMoreBtn.dataset.total);
        const perPage = parseInt(loadMoreBtn.dataset.perPage);
        let currentShown = parseInt(shownCountSpan ? shownCountSpan.textContent : 0);

        function createSkeletonCards(count = 3) {
            let html = '';
            for (let i = 0; i < count; i++) {
                html += `
                    <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-[#C9D3DD]/30">
                        <div class="relative h-52 skeleton-card"></div>
                        <div class="p-5 space-y-3">
                            <div class="h-5 skeleton-card rounded w-3/4"></div>
                            <div class="h-3 skeleton-card rounded w-1/2"></div>
                            <div class="h-8 skeleton-card rounded w-full mt-4"></div>
                        </div>
                    </div>
                `;
            }
            return html;
        }

        function updateShownCount(newCount) {
            currentShown += newCount;
            if (shownCountSpan) {
                shownCountSpan.textContent = currentShown;
            }
        }

        async function loadMore() {
            if (isLoading || currentPage > lastPage) return;
            
            isLoading = true;
            loadMoreBtn.classList.add('loading');
            const originalText = loadMoreBtn.innerHTML;
            loadMoreBtn.innerHTML = `
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                ĐANG TẢI...
            `;

            const skeletons = document.createElement('div');
            skeletons.className = 'contents';
            skeletons.innerHTML = createSkeletonCards(3);
            hotelGrid.appendChild(skeletons);

            try {
                const url = new URL(window.location.href);
                url.searchParams.set('page', currentPage);
                
                const response = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newCards = doc.querySelectorAll('#allHotelsGrid > .hotel-card');
                
                skeletons.remove();
                
                if (newCards.length > 0) {
                    newCards.forEach(card => hotelGrid.appendChild(card));
                    updateShownCount(newCards.length);
                    currentPage++;
                    loadMoreBtn.dataset.nextPage = currentPage;
                    
                    if (currentPage > lastPage) {
                        const doneMsg = document.createElement('div');
                        doneMsg.className = 'mt-10 text-center text-[#C9D3DD] text-sm';
                        doneMsg.innerHTML = `
                            <div class="inline-flex items-center gap-2 bg-[#F8FAFC] px-6 py-3 rounded-full">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Đã hiển thị tất cả <span>${currentShown}</span> khách sạn
                            </div>
                        `;
                        if (loadMoreContainer) loadMoreContainer.replaceWith(doneMsg);
                        else loadMoreBtn.closest('.mt-10').replaceWith(doneMsg);
                    } else {
                        loadMoreBtn.classList.remove('loading');
                        loadMoreBtn.innerHTML = originalText;
                        if (shownCountSpan) {
                            shownCountSpan.style.transition = 'all 0.3s';
                            shownCountSpan.style.color = '#0E5ED8';
                            shownCountSpan.style.fontWeight = 'bold';
                            setTimeout(() => {
                                shownCountSpan.style.color = '';
                                shownCountSpan.style.fontWeight = '';
                            }, 500);
                        }
                    }

                    if (typeof buildHotelDataFromDOM === 'function') buildHotelDataFromDOM();
                }
            } catch (error) {
                console.error('Load more error:', error);
                skeletons.remove();
                loadMoreBtn.classList.remove('loading');
                loadMoreBtn.innerHTML = originalText;
                if (typeof showToast === 'function') showToast('Có lỗi xảy ra, vui lòng thử lại!');
                else alert('Có lỗi xảy ra, vui lòng thử lại!');
            }
            isLoading = false;
        }

        loadMoreBtn.addEventListener('click', loadMore);
    })();

    // ========================
    // RENDER HOTEL GRID (Dùng cho Filter)
    // ========================
    window.renderHotelGrid = function(hotels, city = ''){
        const grid = document.getElementById('allHotelsGrid');
        if (!grid) return;
        
        if (!hotels || hotels.length === 0) {
            grid.innerHTML = `
                <div class="col-span-3 text-center py-20 text-[#C9D3DD]">
                    <p class="text-6xl mb-4 flex justify-center">
                        <svg class="w-16 h-16 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </p>
                    <p class="text-lg text-[#3A4A5A]">Không tìm thấy khách sạn phù hợp</p>
                    <a href="/" class="text-[#87CEFA] underline mt-2 inline-block city-reset-link">Xem tất cả</a>
                </div>
            `;
            return;
        }
        
        grid.innerHTML = hotels.map(hotel => {
            const price = hotel.rooms_min_price ? new Intl.NumberFormat('vi-VN').format(hotel.rooms_min_price) : null;
            return `
            <div class="hotel-card bg-white rounded-2xl overflow-hidden shadow-sm border border-[#C9D3DD]/30 click-effect cursor-pointer"
                onclick="window.location.href='/hotels/${hotel.id}'">
                <div class="relative h-52 bg-[#EAF3FF] overflow-hidden">
                    <img src="https://picsum.photos/seed/${hotel.id}/800/600" alt="${escapeHtml(hotel.name)}" class="w-full h-full object-cover transition duration-500 hover:scale-110">
                    <div class="absolute top-3 left-3">
                        <div class="bg-white/95 backdrop-blur-sm px-2 py-1 rounded-lg flex items-center gap-1 shadow-sm">
                            <svg class="w-3 h-3 text-[#87CEFA]" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                            <span class="text-[#3A4A5A] text-xs font-bold">${hotel.star_rating} Sao</span>
                        </div>
                    </div>
                    <button onclick="event.stopPropagation(); toggleWishlist(${hotel.id}, this)" class="absolute top-3 right-3 bg-white/95 backdrop-blur-sm w-8 h-8 rounded-full flex items-center justify-center text-[#C9D3DD] hover:text-red-500 transition wishlist-btn shadow-sm" data-hotel="${hotel.id}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    </button>
                </div>
                <div class="p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1">
                            <h3 class="font-semibold text-[#1E3A5F] text-base leading-snug">${escapeHtml(hotel.name)}</h3>
                            <p class="text-[#C9D3DD] text-xs mt-1.5 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                ${escapeHtml(hotel.location?.name || city)}
                            </p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <svg class="w-4 h-4 text-[#87CEFA]" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                            <span class="text-sm font-semibold text-[#3A4A5A]">${hotel.avg_rating || '4.5'}</span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-[#EAF3FF] flex items-center justify-between">
                        <div>
                            ${price ? `<p class="text-xs text-[#C9D3DD]">Giá từ</p><p class="font-bold text-[#87CEFA] text-lg">${price}<span class="text-xs font-normal text-[#C9D3DD]">/đêm</span></p>` : '<p class="text-xs text-[#C9D3DD]">Liên hệ</p>'}
                        </div>
                        <span class="text-xs btn-primary px-4 py-2 rounded-lg font-semibold inline-flex items-center gap-1">
                            Xem chi tiết <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </span>
                    </div>
                </div>
            </div>
            `;
        }).join('');
        
        if (typeof buildHotelDataFromDOM === 'function') buildHotelDataFromDOM();
    };

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    document.addEventListener('click', function(e) {
        if (e.target.closest('.city-reset-link')) {
            e.preventDefault();
            window.location.href = '/';
        }
    });

    // ========================
    // TOGGLE WISHLIST
    // ========================
    async function toggleWishlist(hotelId, btn) {
        const token = localStorage.getItem('token');
        if (!token) { 
            if(typeof showToast === 'function') showToast('Vui lòng đăng nhập để lưu yêu thích!');
            else alert('Vui lòng đăng nhập để lưu yêu thích!');
            return; 
        }
        try {
            const res  = await api(`/wishlist/${hotelId}/toggle`, { method: 'POST' });
            const data = await res.json();
            const fill = data.liked ? 'currentColor' : 'none';
            btn.innerHTML = `<svg class="w-4 h-4" fill="${fill}" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>`;
            btn.style.color = data.liked ? '#ef4444' : '';
            if(typeof showToast === 'function') showToast(data.liked ? '❤️ Đã lưu yêu thích' : 'Đã bỏ yêu thích');
        } catch (e) {
            console.error('Lỗi khi lưu yêu thích:', e);
        }
    }

    {{-- resources/views/partials/home/hotels/script.blade.php --}}

    document.addEventListener('DOMContentLoaded', function() {
        const swiper = new Swiper('.featuredSwiper', {
            slidesPerView: 1,
            centeredSlides: true,
            loop: true,
            spaceBetween: 30,
            autoplay: {
                delay: 1500,              // đồng bộ tốc độ với Flash Sale
                disableOnInteraction: false,
                pauseOnMouseEnter: true,  // hover dừng, rời chuột tự chạy lại
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            breakpoints: {
                640: { slidesPerView: 2 },
                1024: { slidesPerView: 3 },
            },
            effect: 'coverflow',
            coverflowEffect: {
                rotate: 15,       // góc xoay 3D giống rotateY bên Flash Sale
                stretch: 0,
                depth: 120,       // độ sâu Z tạo cảm giác nổi
                modifier: 1.5,
                slideShadows: false,
            },
            speed: 500,           // duration transition khớp với Flash Sale (0.5s)
        });
    });

    // ========================
    // CITY FILTER TỪ NAVBAR DROPDOWN
    // ========================
    document.addEventListener('click', async function(e) {
        const link = e.target.closest('.city-filter-link');
        if (!link) return;
        e.preventDefault();

        const city = link.dataset.city;
        if (!city) return;

        // Highlight active
        document.querySelectorAll('.city-filter-link')
                .forEach(l => l.classList.remove('active-scroll-spy'));
        link.classList.add('active-scroll-spy');

        // Ẩn Load More khi filter
        const loadMoreContainer = document.getElementById('loadMoreContainer');
        if (loadMoreContainer) loadMoreContainer.style.display = 'none';

        // Show skeleton loading
        const grid = document.getElementById('allHotelsGrid');
        if (grid) {
            grid.innerHTML = Array(3).fill(`
                <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-[#C9D3DD]/30">
                    <div class="h-52 bg-slate-200 animate-pulse"></div>
                    <div class="p-5 space-y-3">
                        <div class="h-5 bg-slate-200 animate-pulse rounded w-3/4"></div>
                        <div class="h-3 bg-slate-200 animate-pulse rounded w-1/2"></div>
                        <div class="h-8 bg-slate-200 animate-pulse rounded w-full mt-4"></div>
                    </div>
                </div>
            `).join('');
        }

        try {
            const res = await fetch(`/api/hotels?city=${encodeURIComponent(city)}&per_page=9`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            // data.data vì controller trả về paginate()
            window.renderHotelGrid(data.data, city);

            // Cập nhật tiêu đề section
            const heading = document.querySelector('#allHotelsGrid')
                                    ?.closest('section')
                                    ?.querySelector('h2');
            if (heading) heading.textContent = `Khách sạn tại ${city}`;

            // Scroll xuống grid
            grid?.scrollIntoView({ behavior: 'smooth', block: 'start' });

            if (typeof showToast === 'function') showToast(`📍 ${data.data?.length || 0} khách sạn tại ${city}`);

        } catch (err) {
            console.error('City filter error:', err);
            if (typeof showToast === 'function') showToast('Có lỗi xảy ra, vui lòng thử lại!');
        }
    });
</script>