<div class="fixed bottom-6 right-6 z-40">
    <button onclick="toggleChat()" id="chatToggleBtn" class="w-14 h-14 rounded-full flex items-center justify-center shadow-xl transition-all hover:scale-110" style="background: linear-gradient(135deg, #87CEFA, #5BB8F5); color: #1E3A5F;">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
    </button>
</div>

<div id="chat-popup" class="fixed bottom-24 right-6 z-40 hidden w-96 transition-all duration-300">
    <div class="bg-white rounded-2xl shadow-2xl border border-[#EAF3FF] overflow-hidden">
        <div class="px-5 py-4 flex items-center justify-between" style="background: linear-gradient(135deg, #1E3A5F, #0F3460);">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-[#87CEFA] flex items-center justify-center">🤖</div>
                <p class="text-white font-semibold text-sm">AI HolidayViet</p>
            </div>
            <button onclick="toggleChat()" class="text-white/60 hover:text-white">&times;</button>
        </div>
        <div id="chat-messages" class="h-80 overflow-y-auto p-4 space-y-3 bg-[#F8FBFF]"></div>
        <div class="p-4 bg-white border-t flex gap-2">
            <input id="chat-input" type="text" placeholder="Nhập tin nhắn..." class="flex-1 px-4 py-2 border rounded-xl text-sm focus:outline-none" onkeypress="if(event.key==='Enter') sendChat()">
            <button onclick="sendChat()" class="w-10 h-10 rounded-xl bg-[#87CEFA] flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
            </button>
        </div>
    </div>
</div>