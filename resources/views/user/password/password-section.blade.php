<div class="page-header">
    <div>
        <p class="eyebrow">AN TOÀN TÀI KHOẢN</p>
        <h1>Bảo mật tài khoản</h1>
        <p class="page-description">Cập nhật mật khẩu đăng nhập Angel của bạn.</p>
    </div>
</div>

<div class="security-grid">
    <section class="card security-password-card" id="section-password" aria-labelledby="passwordFormTitle">
        <div class="card-header security-card-header">
            <div>
                <h2 id="passwordFormTitle">Đổi mật khẩu</h2>
                <p>Sau khi cập nhật, các phiên khách hàng khác sẽ cần đăng nhập lại.</p>
            </div>
            <span class="security-header-icon">@include('user.partials.icon', ['name'=>'lock'])</span>
        </div>

        <form class="card-body security-password-form" onsubmit="event.preventDefault(); savePassword()" id="passwordForm" novalidate>
            <div class="inline-alert success" id="pwSuccess" role="status" aria-live="polite"><span id="pwSuccessMsg"></span></div>
            <div class="inline-alert error" id="pwError" role="alert"><span id="pwErrorMsg"></span></div>

            <div class="security-form-fields">
                <div class="form-group">
                    <label class="form-label" for="pwCurrent">Mật khẩu hiện tại</label>
                    <input id="pwCurrent" name="current_password" class="form-input" type="password" autocomplete="current-password" maxlength="4096" required aria-describedby="pwCurrentError">
                    <p class="field-error" id="pwCurrentError" aria-live="polite"></p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pwNew">Mật khẩu mới</label>
                    <input id="pwNew" name="new_password" class="form-input" type="password" autocomplete="new-password" minlength="8" maxlength="72" required aria-describedby="pwNewHelp pwNewError">
                    <div class="strength-meter" aria-hidden="true">@for($i=0;$i<4;$i++)<span class="strength-segment"></span>@endfor</div>
                    <div class="strength-caption" id="pwNewHelp"><span>8–72 ký tự</span><span id="strengthText">Chưa nhập</span></div>
                    <p class="field-error" id="pwNewError" aria-live="polite"></p>
                </div>

            </div>

            <div class="form-actions security-form-actions">
                <p>Không chia sẻ mật khẩu hoặc mã OTP với bất kỳ ai.</p>
                <button class="button" type="submit" id="btnSavePw">Cập nhật mật khẩu @include('user.partials.icon', ['name'=>'arrow-right'])</button>
            </div>
        </form>
    </section>

    <aside class="account-aside security-aside" aria-label="Hướng dẫn bảo mật">
        <section class="card aside-note">
            @include('user.partials.icon', ['name'=>'shield'])
            <h2>Mật khẩu an toàn hơn</h2>
            <ul class="policy-list">
                <li>Dùng mật khẩu riêng cho tài khoản Angel.</li>
                <li>Nên kết hợp chữ, số và ký tự đặc biệt.</li>
                <li>Không dùng lại mật khẩu ở dịch vụ khác.</li>
            </ul>
        </section>
        <section class="card aside-note security-google-note">
            <h2>Đăng nhập bằng Google</h2>
            <p>Luồng đổi mật khẩu hiện tại cần mật khẩu Angel đang dùng. Nếu bạn chưa từng thiết lập mật khẩu, hãy dùng “Quên mật khẩu” tại trang đăng nhập để xác minh email và tạo mật khẩu an toàn.</p>
            <a class="text-link" href="/login">Đến trang đăng nhập @include('user.partials.icon', ['name'=>'arrow-right'])</a>
        </section>
    </aside>
</div>
