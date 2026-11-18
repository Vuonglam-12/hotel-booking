<div class="page-header">
    <div>
        <p class="eyebrow">HỒ SƠ CỦA BẠN</p>
        <h1>Thông tin cá nhân</h1>
        <p class="page-description">Quản lý thông tin liên hệ được sử dụng cho tài khoản và đặt phòng.</p>
    </div>
</div>

<div id="profileLoadState" role="status" class="loading-state">
    @include('user.partials.icon', ['name' => 'spinner']) Đang tải hồ sơ…
</div>

<div id="profileContent" class="profile-content" hidden>
    <section class="card profile-account-card" aria-labelledby="profileAccountName">
        <div class="profile-avatar" aria-hidden="true">
            <img id="profileAvatarImg" alt="">
            <span id="profileAvatarInitial">?</span>
        </div>
        <div class="profile-account-copy">
            <p class="profile-account-label">TÀI KHOẢN Angel</p>
            <h2 id="profileAccountName">—</h2>
            <p id="profileAccountEmail">—</p>
        </div>
        <div class="profile-avatar-actions">
            <button type="button" id="avatarUploadButton" class="button secondary" onclick="document.getElementById('avatarInput').click()">
                @include('user.partials.icon', ['name' => 'camera']) Thay ảnh đại diện
            </button>
            <input type="file" id="avatarInput" accept="image/jpeg,image/png,image/webp" onchange="handleAvatarUpload(this)" hidden>
            <p id="profileAvatarStatus" class="profile-avatar-status" role="status" aria-live="polite"></p>
        </div>
    </section>

    <div class="profile-content-grid">
        <section class="card" aria-labelledby="profileFormTitle">
            <div class="card-header">
                <div>
                    <h2 id="profileFormTitle">Thông tin cơ bản</h2>
                    <p>Thông tin được sử dụng cho tài khoản và các đặt phòng của bạn.</p>
                </div>
                @include('user.partials.icon', ['name' => 'user-round'])
            </div>
            <form class="card-body" id="profileForm" onsubmit="event.preventDefault(); saveProfile()">
                <div class="inline-alert success" id="profileSuccess"><span id="profileSuccessMsg"></span></div>
                <div class="inline-alert error" id="profileError"><span id="profileErrorMsg"></span></div>
                <div class="form-grid">
                    <div class="form-group full">
                        <label class="form-label" for="profileName">Họ và tên <span aria-hidden="true">*</span></label>
                        <input class="form-input" id="profileName" name="name" autocomplete="name" required maxlength="255">
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="profileEmail">Email đăng nhập</label>
                        <input class="form-input" id="profileEmail" name="email" type="email" autocomplete="email" readonly aria-describedby="profileEmailHint">
                        <p class="form-hint" id="profileEmailHint">Email dùng để đăng nhập và hiện chưa thể thay đổi tại đây.</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="profilePhone">Số điện thoại</label>
                        <input class="form-input" id="profilePhone" name="phone" type="tel" autocomplete="tel" maxlength="20" placeholder="Chưa cập nhật">
                    </div>
                    <div class="form-group" id="profileDobField">
                        <label class="form-label" for="profileDob">Ngày sinh</label>
                        <input class="form-input" id="profileDob" name="dob" type="date" max="{{ now()->toDateString() }}" autocomplete="bday" disabled>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button" id="btnSaveProfile" disabled>Lưu thay đổi @include('user.partials.icon', ['name' => 'arrow-right'])</button>
                </div>
            </form>
        </section>

        <aside aria-label="Tóm tắt đặt phòng">
            <section class="card stat-card profile-booking-stat">
                <div class="stat-label">@include('user.partials.icon', ['name' => 'calendar-check']) Tổng số đặt phòng</div>
                <p class="stat-number" id="stat-bookings">—</p>
                <a class="text-link" href="/bookings">Xem lịch sử đặt phòng @include('user.partials.icon', ['name' => 'arrow-right'])</a>
            </section>
        </aside>
    </div>

    @include('user.profile.wishlist-section')
</div>
