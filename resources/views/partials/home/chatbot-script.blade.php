<script>
    let chatSessionId = null;

    function toggleChat() {
        const popup = document.getElementById('chat-popup');
        popup.classList.toggle('hidden');
        if (!popup.classList.contains('hidden') && !chatSessionId) {
            startChatSession();
        }
    }

    async function resetChat() {
        chatSessionId = null;
        const messages = document.getElementById('chat-messages');
        messages.innerHTML = `
            <div class="flex gap-2">
                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #87CEFA, #5BB8F5);">
                    <span class="text-white text-sm">🤖</span>
                </div>
                <div class="bg-white px-4 py-2.5 rounded-2xl rounded-tl-sm shadow-sm border border-[#EAF3FF] max-w-[85%]">
                    <p class="text-sm text-[#1E3A5F]">Bắt đầu cuộc trò chuyện mới! Bạn cần tôi giúp gì? 🌟</p>
                    <p class="text-xs text-[#C9D3DD] mt-2 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Vừa xong
                    </p>
                </div>
            </div>
        `;
        await startChatSession();
    }

    async function startChatSession() {
        const token = localStorage.getItem('token');
        if (!token) { 
            addChatMessage('bot', 'Vui lòng <a href="/login" class="text-[#87CEFA] underline font-semibold">đăng nhập</a> để chat với AI!'); 
            return; 
        }
        try {
            const res = await api('/chat/start', { method: 'POST' });
            const data = await res.json();
            chatSessionId = data.session_id;
        } catch (e) {
            console.error("Lỗi khi kết nối Chatbot:", e);
        }
    }

    function sendQuickMessage(msg) {
        document.getElementById('chat-input').value = msg;
        sendChat();
    }

    async function sendChat() {
        const input = document.getElementById('chat-input');
        const msg = input.value.trim();
        if (!msg) return;
        
        addChatMessage('user', msg);
        input.value = '';
        
        if (!chatSessionId) { 
            addChatMessage('bot', 'Vui lòng <a href="/login" class="text-[#87CEFA] underline font-semibold">đăng nhập</a>!'); 
            return; 
        }
        
        // Hiển thị typing indicator
        const typingId = addTypingIndicator();
        
        try {
            const res = await api(`/chat/${chatSessionId}/message`, { 
                method: 'POST', 
                body: JSON.stringify({ message: msg }) 
            });
            const data = await res.json();
            
            removeTypingIndicator(typingId);
            
            if (res.ok) {
                addChatMessage('bot', data.message);
            } else {
                addChatMessage('bot', '❌ ' + (data.message || 'Có lỗi xảy ra, thử lại sau!'));
            }
        } catch (e) {
            removeTypingIndicator(typingId);
            addChatMessage('bot', '❌ Lỗi kết nối AI, vui lòng thử lại!');
        }
    }

    function addTypingIndicator() {
        const messages = document.getElementById('chat-messages');
        const id = 'typing-' + Date.now();
        const div = document.createElement('div');
        div.id = id;
        div.className = 'flex gap-2';
        div.innerHTML = `
            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #87CEFA, #5BB8F5);">
                <span class="text-white text-sm">🤖</span>
            </div>
            <div class="bg-white px-4 py-2.5 rounded-2xl rounded-tl-sm shadow-sm border border-[#EAF3FF]">
                <div class="flex gap-1">
                    <span class="w-2 h-2 bg-[#C9D3DD] rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                    <span class="w-2 h-2 bg-[#C9D3DD] rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                    <span class="w-2 h-2 bg-[#C9D3DD] rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                </div>
            </div>
        `;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        return id;
    }

    function removeTypingIndicator(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    function startVoiceInput() {
        if(typeof showToast === 'function') showToast('🎤 Tính năng đang phát triển!', 'info');
        else alert('🎤 Tính năng đang phát triển!');
    }

    function addChatMessage(role, text) {
        const messages = document.getElementById('chat-messages');
        const isBot    = role === 'bot';
        const div      = document.createElement('div');
        div.className  = `flex gap-2 ${isBot ? '' : 'flex-row-reverse'}`;
        div.innerHTML  = `
            ${isBot ? '<div class="w-8 h-8 bg-[#87CEFA] rounded-full flex items-center justify-center shrink-0"><span class="text-sm">🤖</span></div>' : ''}
            <div class="px-3 py-2 text-sm max-w-[85%] shadow-sm rounded-2xl ${isBot ? 'bg-white border border-[#EAF3FF] text-[#1E3A5F] rounded-tl-sm' : 'bg-[#0E5ED8] text-white rounded-tr-sm'}">${text}</div>`;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }
</script>