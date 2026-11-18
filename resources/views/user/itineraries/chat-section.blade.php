<div class="ai-app" id="aurelAi" data-mode="welcome" data-history-open="false">
    <button class="ai-history-backdrop" id="aiHistoryBackdrop" type="button" aria-label="Đóng lịch sử" hidden></button>
    <aside class="ai-history" id="aiHistory" aria-label="Lịch sử trò chuyện" aria-hidden="true" inert>
        <div class="ai-history-heading">
            <h3 class="ai-history-title">Lịch sử trò chuyện</h3>
            <button class="ai-icon-button" id="aiCloseHistory" type="button" aria-label="Thu gọn lịch sử" aria-controls="aiHistory" aria-expanded="true" title="Thu gọn lịch sử">@include('user.partials.icon', ['name' => 'menu'])</button>
        </div>
        <button class="ai-history-new" type="button" data-ai-new>@include('user.partials.icon', ['name' => 'plus']) Cuộc trò chuyện mới</button>
        <div class="ai-history-list" id="aiHistoryList"></div>
        <div class="ai-history-footer">
            <p>Lịch sử được lưu theo tài khoản của bạn.</p>
        </div>
    </aside>

    <div class="ai-workspace">
        <header class="ai-topbar">
            <div class="ai-topbar-start">
                <button class="ai-icon-button" id="aiToggleHistory" type="button" aria-controls="aiHistory" aria-expanded="false" aria-label="Mở lịch sử trò chuyện" title="Lịch sử trò chuyện">@include('user.partials.icon', ['name' => 'menu'])</button>
            </div>
            <div class="ai-topbar-actions">
                <details class="ai-examples" id="aiExamples">
                    <summary class="ai-small-button">@include('user.partials.icon', ['name' => 'star']) <span>Gợi ý</span></summary>
                    <div class="ai-examples-menu" aria-label="Gợi ý cho cuộc trò chuyện">
                        <p>BẠN MUỐN BẮT ĐẦU TỪ ĐÂU?</p>
                        <button type="button" data-ai-example="places">@include('user.partials.icon', ['name' => 'search']) Địa điểm tham quan</button>
                        <button type="button" data-ai-example="hotels">@include('user.partials.icon', ['name' => 'hotel']) Khách sạn</button>
                        <button type="button" data-ai-example="timeline">@include('user.partials.icon', ['name' => 'calendar-days']) Lịch trình nhiều ngày</button>
                        <button type="button" data-ai-example="budget">@include('user.partials.icon', ['name' => 'wallet']) Tóm tắt ngân sách</button>
                        <button type="button" data-ai-example="route">@include('user.partials.icon', ['name' => 'route']) Sơ đồ tuyến đường</button>
                    </div>
                </details>
                <button class="ai-small-button ai-new-top" type="button" data-ai-new aria-label="Tạo cuộc trò chuyện mới" title="Cuộc trò chuyện mới">@include('user.partials.icon', ['name' => 'plus']) <span>Trò chuyện mới</span></button>
                <button class="ai-icon-button ai-close-conversation" id="aiCloseConversation" type="button" aria-label="Xóa hội thoại hiện tại" title="Xóa hội thoại hiện tại" hidden>@include('user.partials.icon', ['name' => 'x'])</button>
            </div>
        </header>
        <p class="ai-session-notice" id="aiSessionNotice" role="status" aria-live="polite" hidden></p>

        <div class="ai-scroll" id="aiScroll">
            <section class="ai-welcome" id="aiWelcome" aria-labelledby="aiWelcomeTitle">
                <p class="ai-welcome-description" id="aiWelcomeTitle">Hỏi về địa điểm hoặc nhờ AI điều chỉnh lịch trình.</p>
                <div id="aiWelcomeComposerSlot" class="ai-welcome-composer-slot"></div>
            </section>
            <section class="ai-messages" id="aiMessages" role="log" aria-label="Nội dung trò chuyện" aria-live="polite" aria-relevant="additions" hidden></section>
        </div>

        <button class="ai-jump" id="aiJump" type="button" hidden>@include('user.partials.icon', ['name' => 'arrow-right']) Tin nhắn mới nhất</button>
        <div class="ai-composer-dock" id="aiComposerDock" hidden>
            <form class="ai-composer" id="aiComposer" aria-label="Gửi tin nhắn cho trợ lý du lịch">
                <label class="sr-only" for="aiInput">Bạn muốn khám phá điều gì?</label>
                <textarea id="aiInput" rows="2" maxlength="2000" placeholder="Bạn muốn điều chỉnh gì cho chuyến đi?" enterkeyhint="send"></textarea>
                <div class="ai-composer-tools"><div class="ai-composer-right"><span class="ai-input-count" id="aiInputCount" hidden>0/2000</span><button class="ai-send" id="aiSend" type="submit" aria-label="Gửi tin nhắn" title="Gửi tin nhắn" disabled><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button></div></div>
            </form>
            <p class="ai-composer-disclaimer" id="aiComposerDisclaimer">AI có thể nhầm. Kiểm tra nguồn và các giả định trước khi đi. <span>Enter gửi · Shift + Enter xuống dòng</span></p>
        </div>
        <span class="sr-only" id="aiAnnouncer" role="status" aria-live="polite"></span>
    </div>
</div>
