<!-- resources/views/partials/home/about-script.blade.php -->
<style>
    /* Keyframes cho hiệu ứng nền lơ lửng (Blob) */
    @keyframes blob {
        0% { transform: translate(0px, 0px) scale(1); }
        33% { transform: translate(30px, -50px) scale(1.1); }
        66% { transform: translate(-20px, 20px) scale(0.9); }
        100% { transform: translate(0px, 0px) scale(1); }
    }
    .animate-blob { 
        animation: blob 7s infinite; 
    }
    .animation-delay-2000 { 
        animation-delay: 2s; 
    }
    .animation-delay-4000 { 
        animation-delay: 4s; 
    }

    /* Keyframes cho khối Glassmorphism trôi nổi */
    @keyframes float {
        0% { transform: translateY(0px); }
        50% { transform: translateY(-15px); }
        100% { transform: translateY(0px); }
    }
    .animate-float { 
        animation: float 4s ease-in-out infinite; 
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Thiết lập Intersection Observer cho hiệu ứng scroll reveal
        const revealElements = document.querySelectorAll('.reveal-left, .reveal-right');

        const revealCallback = (entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Khi cuộn tới, gỡ bỏ class ẩn và thêm class hiển thị
                    entry.target.classList.add('opacity-100', 'translate-x-0', 'translate-y-0');
                    entry.target.classList.remove('opacity-0', '-translate-x-12', 'translate-x-12');
                    observer.unobserve(entry.target); // Chỉ chạy 1 lần
                }
            });
        };

        const revealOptions = {
            threshold: 0.2, // Chạy khi 20% element xuất hiện
            rootMargin: "0px 0px -50px 0px"
        };

        const revealObserver = new IntersectionObserver(revealCallback, revealOptions);

        revealElements.forEach(el => {
            // Set class khởi tạo ban đầu để chuẩn bị animate
            el.classList.add('transition-all', 'duration-[1200ms]', 'ease-out', 'opacity-0');
            
            if(el.classList.contains('reveal-left')) {
                el.classList.add('-translate-x-12'); // Bay từ trái sang
            } else if(el.classList.contains('reveal-right')) {
                el.classList.add('translate-x-12'); // Bay từ phải sang
            }
            
            revealObserver.observe(el);
        });
    });
</script>