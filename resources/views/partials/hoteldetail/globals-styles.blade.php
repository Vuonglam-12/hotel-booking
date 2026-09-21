<style>
    /* Soft Sky Color Scheme - Hệ màu chủ đạo */
    :root {
        --soft-sky: #87CEFA;
        --soft-bg: #EAF3FF;
        --white: #FFFFFF;
        --soft-gray: #C9D3DD;
        --dark-soft: #3A4A5A;
    }
    
    /* Font chữ đồng bộ */
    .font-display {
        font-family: 'Playfair Display', serif;
    }
    
    /* Hiệu ứng card nổi */
    .info-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .info-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px -5px rgba(58, 74, 90, 0.1);
    }
    
    /* Nút chính xanh Sky */
    .btn-primary {
        background: var(--soft-sky);
        color: var(--dark-soft);
        transition: all 0.3s ease;
        font-weight: 600;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -5px rgba(135, 206, 250, 0.4);
        background: #7BC4F5;
    }
    
    .btn-primary:active {
        transform: translateY(0);
    }
    
    /* Nút vàng Gold */
    .btn-gold {
        background: linear-gradient(135deg, #F59E0B, #FBBF24);
        color: white;
        transition: all 0.3s ease;
        font-weight: 600;
    }
    
    .btn-gold:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -5px rgba(245, 158, 11, 0.4);
    }
    
    .btn-gold:active {
        transform: translateY(0);
    }
    
    /* Hiệu ứng click nhẹ */
    .click-effect:active {
        transform: scale(0.98);
        transition: transform 0.1s ease;
    }
    
    /* Room item hover effect */
    .room-item {
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .room-item:hover {
        border-color: var(--soft-sky);
        background: var(--soft-bg);
        transform: translateX(4px);
    }
    
    /* Sidebar sticky - Cố định khi cuộn trang */
    .sidebar-sticky {
        position: sticky;
        top: 20px;
        transition: all 0.3s ease;
    }
    
    .sidebar-sticky:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 30px -10px rgba(58, 74, 90, 0.15);
    }
    
    /* Rating badge trong suốt nhòe */
    .rating-badge {
        background: rgba(58, 74, 90, 0.9);
        backdrop-filter: blur(10px);
    }
    
    /* Icons tiện ích */
    .amenity-icon {
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .amenity-icon:hover {
        transform: scale(1.05);
        background: var(--soft-bg);
    }
    
    /* Line height fix cho văn bản */
    p, h1, h2, h3, .text-content {
        line-height: 1.5;
    }
    
    .section-title {
        line-height: 1.3;
        margin-bottom: 1rem;
    }

    /* Animation Loading & Success */
    @keyframes spin {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }
    .animate-spin {
        animation: spin 1s linear infinite;
    }

    @keyframes fadeScaleIn {
        from { opacity: 0; transform: scale(0.5); }
        to   { opacity: 1; transform: scale(1); }
    }
    .animate-success {
        animation: fadeScaleIn 0.4s ease forwards;
    }

    /* Overlay cho Modal thanh toán */
    #payment-overlay {
        position: absolute;
        inset: 0;
        background: rgba(255,255,255,0.97);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border-radius: 1rem;
        z-index: 10;
    }
</style>