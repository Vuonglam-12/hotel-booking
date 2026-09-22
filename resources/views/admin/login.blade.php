{{-- resources/views/admin/login.blade.php --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Quản trị Admin — HolidayViet</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --sky:      #87CEFA;
            --sky-dark: #7BC4F5;
            --bg:       #EAF3FF;
            --dark:     #3A4A5A;
            --dark2:    #2A3A4A;
            --gray:     #C9D3DD;
            --gold:     #F1C40F;
            --gold2:    #F59E0B;
            --white:    #FFFFFF;
            --muted:    rgba(58,74,90,0.55);
            --border:   rgba(135,206,250,0.25);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Nunito Sans', system-ui, sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }

        /* ── Background ── */
        .bg-mesh {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background:
                radial-gradient(ellipse 55% 45% at 15% 15%, rgba(135,206,250,0.18) 0%, transparent 65%),
                radial-gradient(ellipse 45% 55% at 85% 85%, rgba(135,206,250,0.12) 0%, transparent 65%),
                linear-gradient(160deg, #D6EEFF 0%, #EAF3FF 50%, #DBF0FF 100%);
        }
        .particle {
            position: absolute; border-radius: 50%;
            background: rgba(135,206,250,0.45);
            animation: rise linear infinite;
        }
        @keyframes rise {
            0%   { transform: translateY(110vh) scale(0); opacity: 0; }
            8%   { opacity: 1; }
            92%  { opacity: 1; }
            100% { transform: translateY(-10vh) scale(1); opacity: 0; }
        }

        /* ── Shell ── */
        .shell {
            position: relative; z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            max-width: 880px;
            min-height: 580px;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(135,206,250,0.35);
            box-shadow:
                0 40px 80px -20px rgba(58,74,90,0.18),
                0 0 0 1px rgba(255,255,255,0.6) inset;
            animation: fadeUp 0.55s cubic-bezier(.22,1,.36,1) both;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ══════════════ LEFT PANEL ══════════════ */
        .left {
            background: linear-gradient(160deg, #3A4A5A 0%, #2A3A4A 100%);
            display: flex; flex-direction: column;
            padding: 44px 40px;
            position: relative; overflow: hidden;
        }
        .left::before {
            content: '';
            position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%2387CEFA' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        .left-glow {
            position: absolute; width: 300px; height: 300px; border-radius: 50%;
            background: radial-gradient(circle, rgba(135,206,250,0.12) 0%, transparent 70%);
            top: -80px; right: -80px; pointer-events: none;
        }
        .left-glow2 {
            position: absolute; width: 200px; height: 200px; border-radius: 50%;
            background: radial-gradient(circle, rgba(135,206,250,0.08) 0%, transparent 70%);
            bottom: -60px; left: -60px; pointer-events: none;
        }

        /* Logo */
        .logo-link {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; width: fit-content;
            transition: opacity 0.2s;
        }
        .logo-link:hover { opacity: 0.85; }
        .logo-svg { width: 36px; height: 36px; filter: drop-shadow(0 2px 6px rgba(0,0,0,0.2)); }
        .logo-wordmark { display: flex; align-items: baseline; }
        .logo-h { font-family: 'Nunito Sans', system-ui, sans-serif; font-size: 22px; font-weight: 700; color: #fff; }
        .logo-v { font-family: 'Nunito Sans', system-ui, sans-serif; font-size: 22px; font-weight: 700; color: var(--gold); }
        .admin-pill {
            background: rgba(241,196,15,0.18);
            border: 1px solid rgba(241,196,15,0.35);
            color: #F1C40F;
            font-size: 10px; font-weight: 700; letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 3px 9px; border-radius: 99px;
            margin-left: 6px; align-self: center;
        }

        /* Hero */
        .hero { margin: auto 0; padding: 32px 0; }
        .hero-title {
            font-family: 'Nunito Sans', system-ui, sans-serif;
            font-size: 32px; font-weight: 700;
            color: #fff; line-height: 1.25;
            margin-bottom: 14px;
        }
        .hero-sub {
            font-size: 13.5px; line-height: 1.7;
            color: rgba(201,211,221,0.8);
            max-width: 260px;
        }

        /* Stats */
        .stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .stat {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(135,206,250,0.15);
            border-radius: 14px; padding: 16px;
            transition: background 0.2s, border-color 0.2s;
        }
        .stat:hover {
            background: rgba(135,206,250,0.1);
            border-color: rgba(135,206,250,0.3);
        }
        .stat-val {
            font-size: 22px; font-weight: 600; color: #fff;
            margin-bottom: 4px;
        }
        .stat-lbl { font-size: 11px; color: rgba(201,211,221,0.6); }
        .stat-accent { color: var(--sky); }

        /* ══════════════ RIGHT PANEL ══════════════ */
        .right {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            display: flex; flex-direction: column; justify-content: center;
            padding: 48px 44px;
        }

        .form-title {
            font-family: 'Nunito Sans', system-ui, sans-serif;
            font-size: 26px; font-weight: 700;
            color: var(--dark); margin-bottom: 6px;
        }
        .form-sub { font-size: 13px; color: var(--muted); margin-bottom: 28px; }

        /* Role selector */
        .role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 24px; }
        .role-opt {
            border: 1px solid rgba(135,206,250,0.3);
            border-radius: 12px; padding: 12px 14px;
            cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; gap: 10px;
            background: rgba(234,243,255,0.6);
        }
        .role-opt:hover { background: rgba(135,206,250,0.12); border-color: var(--sky-dark); }
        .role-opt.selected {
            border-color: var(--sky);
            background: rgba(135,206,250,0.15);
            box-shadow: 0 0 0 3px rgba(135,206,250,0.15);
        }
        .role-opt.selected .role-name { color: var(--dark); font-weight: 600; }
        .role-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .role-name { font-size: 13px; font-weight: 500; color: var(--dark); transition: color 0.2s; }
        .role-desc { font-size: 11px; color: var(--muted); margin-top: 1px; }

        /* Field */
        .field { margin-bottom: 16px; }
        .field-label {
            display: block; font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.6px;
            color: var(--dark); opacity: 0.65;
            margin-bottom: 6px;
        }
        .field-wrap { position: relative; }
        .field-icon {
            position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
            color: var(--sky-dark); display: flex; align-items: center;
            pointer-events: none;
        }
        .field-input {
            width: 100%; height: 44px;
            padding: 0 14px 0 40px;
            background: #fff;
            border: 1px solid rgba(135,206,250,0.4);
            border-radius: 12px;
            color: var(--dark); font-size: 14px; font-family: 'Nunito Sans', system-ui, sans-serif;
            outline: none; transition: all 0.2s;
        }
        .field-input::placeholder { color: var(--gray); }
        .field-input:focus {
            border-color: var(--sky);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(135,206,250,0.2);
        }

        /* Error */
        .error-box {
            background: rgba(239,68,68,0.07);
            border: 1px solid rgba(239,68,68,0.25);
            border-radius: 10px; padding: 11px 14px;
            color: #DC2626; font-size: 13px;
            margin-bottom: 16px;
            display: none; align-items: center; gap: 8px;
        }
        .error-box.show { display: flex; }

        /* Button */
        .btn-login {
            width: 100%; height: 46px;
            background: linear-gradient(135deg, var(--sky), var(--sky-dark));
            color: var(--dark); font-weight: 700; font-size: 14.5px;
            border: none; border-radius: 12px;
            cursor: pointer; transition: all 0.25s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 8px; letter-spacing: 0.2px;
            position: relative; overflow: hidden;
        }
        .btn-login::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.25), transparent);
            opacity: 0; transition: opacity 0.2s;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -6px rgba(135,206,250,0.5); }
        .btn-login:hover::before { opacity: 1; }
        .btn-login:active { transform: translateY(0); box-shadow: none; }
        .btn-login:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }

        .spinner {
            width: 18px; height: 18px; display: none;
            border: 2px solid rgba(58,74,90,0.2);
            border-top-color: var(--dark);
            border-radius: 50%;
            animation: spin 0.65s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Back link */
        .divider { height: 1px; background: rgba(135,206,250,0.25); margin: 22px 0; }
        .back-link {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            color: var(--muted); font-size: 13px;
            text-decoration: none; transition: color 0.2s;
        }
        .back-link:hover { color: var(--dark); }

        /* ── Responsive ── */
        @media (max-width: 640px) {
            .shell { grid-template-columns: 1fr; }
            .left  { display: none; }
            .right { padding: 40px 28px; }
        }
    </style>
</head>
<body>

    <div class="bg-mesh" id="bgMesh"></div>

    <div class="shell">

        {{-- ══ LEFT PANEL ══ --}}
        <div class="left">
            <div class="left-glow"></div>
            <div class="left-glow2"></div>

            {{-- Logo --}}
            <a href="{{ route('home') }}" class="logo-link">
                <div class="relative w-9 h-9">
                    <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" class="logo-svg">
                        <path d="M20 80C20 80 25 25 55 10C55 10 45 40 45 80H20Z" fill="white"/>
                        <path d="M50 75C50 75 55 35 75 20C75 20 65 45 65 75H50Z" fill="#87CEFA"/>
                        <path d="M40 85C60 85 85 65 85 35" stroke="#F1C40F" stroke-width="3" stroke-linecap="round"/>
                        <path d="M55 70L65 75M60 60L75 65M68 50L82 52M75 40L88 38" stroke="#F1C40F" stroke-width="3" stroke-linecap="round"/>
                        <path d="M15 85C35 78 65 78 85 85" stroke="#F1C40F" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="logo-wordmark">
                    <span class="logo-h">Holiday</span>
                    <span class="logo-v">Viet</span>
                </div>
                <span class="admin-pill">Admin</span>
            </a>

            {{-- Hero --}}
            <div class="hero">
                <div class="hero-title">Quản trị hệ thống<br>HolidayViet</div>
                <div class="hero-sub">Nền tảng quản lý khách sạn &amp; đặt phòng trực tuyến toàn quốc. Chỉ dành cho nhân viên và quản trị viên được uỷ quyền.</div>
            </div>

            {{-- Stats --}}
            <div class="stats">
                <div class="stat">
                    <div class="stat-val">21<span class="stat-accent">+</span></div>
                    <div class="stat-lbl">Khách sạn đối tác</div>
                </div>
                <div class="stat">
                    <div class="stat-val">1,284</div>
                    <div class="stat-lbl">Booking tháng này</div>
                </div>
                <div class="stat">
                    <div class="stat-val">642<span class="stat-accent">M</span></div>
                    <div class="stat-lbl">Doanh thu (VNĐ)</div>
                </div>
                <div class="stat">
                    <div class="stat-val">98.2<span class="stat-accent">%</span></div>
                    <div class="stat-lbl">Uptime hệ thống</div>
                </div>
            </div>
        </div>

        {{-- ══ RIGHT PANEL ══ --}}
        <div class="right">
            <div class="form-title">Đăng nhập</div>
            <div class="form-sub">Vui lòng chọn vai trò và nhập thông tin tài khoản</div>

            {{-- Role selector --}}
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.6px;color:rgba(58,74,90,0.6);margin-bottom:8px;">Vai trò của bạn</div>
            <div class="role-grid" id="roleGrid">
                <div class="role-opt selected" onclick="selectRole(this)">
                    <div class="role-dot" style="background:#87CEFA;"></div>
                    <div>
                        <div class="role-name">Quản trị viên</div>
                        <div class="role-desc">Toàn quyền hệ thống</div>
                    </div>
                </div>
                <div class="role-opt" onclick="selectRole(this)">
                    <div class="role-dot" style="background:#34D399;"></div>
                    <div>
                        <div class="role-name">Nhân viên</div>
                        <div class="role-desc">Quyền vận hành</div>
                    </div>
                </div>
            </div>

            {{-- Error --}}
            <div class="error-box" id="error-box">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span id="error-msg">Email hoặc mật khẩu không đúng</span>
            </div>

            {{-- Email --}}
            <div class="field">
                <label class="field-label">Email</label>
                <div class="field-wrap">
                    <span class="field-icon">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                            <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </span>
                    <input type="email" id="email" class="field-input" placeholder="admin@holidayviet.vn" autocomplete="email" />
                </div>
            </div>

            {{-- Password --}}
            <div class="field">
                <label class="field-label">Mật khẩu</label>
                <div class="field-wrap">
                    <span class="field-icon">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                    </span>
                    <input type="password" id="password" class="field-input" placeholder="••••••••" autocomplete="current-password" />
                </div>
            </div>

            {{-- Submit --}}
            <button class="btn-login" id="btn-login" onclick="doLogin()">
                <span id="btn-text">Đăng nhập</span>
                <div class="spinner" id="btn-spinner"></div>
                <svg id="btn-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </button>

            <div class="divider"></div>

            <a href="/" class="back-link">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Quay về trang chủ
            </a>
        </div>
    </div>

    <script>
        // ── Particles ──
        (function () {
            const bg = document.getElementById('bgMesh');
            for (let i = 0; i < 30; i++) {
                const p = document.createElement('div');
                p.className = 'particle';
                const sz = Math.random() * 3 + 1;
                p.style.cssText = `
                    left:${Math.random()*100}%;
                    width:${sz}px; height:${sz}px;
                    animation-duration:${Math.random()*16+10}s;
                    animation-delay:${Math.random()*12}s;
                    opacity:${Math.random()*0.4+0.1};
                `;
                bg.appendChild(p);
            }
        })();

        // ── Role selector ──
        function selectRole(el) {
            document.querySelectorAll('.role-opt').forEach(o => o.classList.remove('selected'));
            el.classList.add('selected');
        }

        // ── Enter key ──
        document.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });

        // ── If already authed → redirect ──
        if (localStorage.getItem('admin_token')) {
            window.location.href = '/admin';
        }

        // ── Login logic (unchanged from original) ──
        async function doLogin() {
            const email    = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const errBox   = document.getElementById('error-box');
            const errMsg   = document.getElementById('error-msg');
            const btn      = document.getElementById('btn-login');
            const spinner  = document.getElementById('btn-spinner');
            const btnIcon  = document.getElementById('btn-icon');
            const btnText  = document.getElementById('btn-text');

            errBox.classList.remove('show');

            if (!email || !password) {
                errMsg.textContent = 'Vui lòng nhập đầy đủ email và mật khẩu';
                errBox.classList.add('show');
                return;
            }

            btn.disabled            = true;
            spinner.style.display   = 'block';
            btnIcon.style.display   = 'none';
            btnText.textContent     = 'Đang xác thực...';

            try {
                const res = await fetch('/api/admin/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: JSON.stringify({ email, password })
                });

                const data = await res.json();

                if (!res.ok) {
                    errMsg.textContent = data.message || 'Email hoặc mật khẩu không đúng';
                    errBox.classList.add('show');
                    return;
                }

                // Lưu admin token riêng — không đụng vào customer token
                localStorage.setItem('admin_token', data.token);
                localStorage.setItem('admin_account', JSON.stringify(data.admin));

                window.location.href = '/admin';

            } catch (err) {
                errMsg.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
                errBox.classList.add('show');
            } finally {
                btn.disabled           = false;
                spinner.style.display  = 'none';
                btnIcon.style.display  = 'block';
                btnText.textContent    = 'Đăng nhập';
            }
        }
    </script>
</body>
</html>