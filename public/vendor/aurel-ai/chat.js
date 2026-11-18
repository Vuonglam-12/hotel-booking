/* AUREL travel assistant. All replies and catalog results come from authenticated Laravel APIs. */
(() => {
    'use strict';
    const app = document.getElementById('aurelAi');if (!app) return;
    const $ = id => document.getElementById(id), input = $('aiInput'), composer = $('aiComposer'), scroll = $('aiScroll');
    const history = $('aiHistory'), workspace = app.querySelector('.ai-workspace'), mobile = matchMedia('(max-width: 767px)');
    const threads = [], jobs = new Map(), maps = new Set(), deletedSessions = new Set();let activeId, composing = false, sequence = 0, historyError = '';
    const customerText = value => String(value || '')
        .replace(/^\s*AUREL\s+(?:·\s*)?(\p{L})/iu,(_,letter)=>letter.toUpperCase())
        .replace(/[\[(]\s*(?:aurel|aruel|attraction|geo):[a-z0-9_-]+\s*[\])]/gi,'')
        .replace(/\b(?:aurel|aruel|attraction|geo):[a-z0-9_-]+\b/gi,'')
        .replace(/Geoapify\s*\/\s*OpenStreetMap/gi,'Bản đồ OpenStreetMap').replace(/Geoapify/gi,'nguồn bản đồ').replace(/MySQL/gi,'hệ thống')
        .replace(/\b(?:tôi|mình)\s+là\s+AUREL\b/giu,'mình là trợ lý du lịch').replace(/\b(?:của|từ)\s+AUREL\b/giu,'').replace(/\bAUREL\b/gi,'')
        .replace(/^[ \t]*[·|][ \t]*/gm,'').replace(/[ \t]+([,.;!?])/g,'$1').replace(/[ \t]{2,}/g,' ').trim();
    const DEFAULT_PROGRESS = 'Đang xem xét yêu cầu của bạn…';
    const PROGRESS_MESSAGES = Object.freeze({
        considering:'Đang suy nghĩ…',processing_request:'Đang xem yêu cầu của bạn…',
        reading_context:'Đang xem lại thông tin chuyến đi…',searching_places:'Đang tìm những điểm đến phù hợp…',
        searching_images:'Đang tìm ảnh cho các địa điểm…',searching_hotels:'Đang tìm khách sạn phù hợp…',
        checking_rooms:'Đang kiểm tra phòng trống và giá…',calculating_route:'Đang tính quãng đường và thời gian di chuyển…',
        creating_itinerary:'Đang sắp xếp lịch trình cho bạn…',revising_itinerary:'Đang điều chỉnh lịch trình của bạn…',
        checking_itinerary:'Đang kiểm tra lại lịch trình…',waiting:'Mình vẫn đang xử lý, bạn chờ một chút nhé…',
    });
    const progressText = state => state?.code === 'revising_day'
        ? (Number.isInteger(state.day) && state.day >= 1 && state.day <= 30 ? 'Đang điều chỉnh lịch trình ngày ' + state.day + '…' : DEFAULT_PROGRESS)
        : (Object.hasOwn(PROGRESS_MESSAGES,state?.code) ? PROGRESS_MESSAGES[state.code] : DEFAULT_PROGRESS);
    const uid = () => typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : Date.now() + '-' + (++sequence) + '-' + Math.random().toString(36).slice(2);
    const token = () => localStorage.getItem('token');
    const current = () => threads.find(t => t.id === activeId);
    const money = n => new Intl.NumberFormat('vi-VN').format(n) + ' ₫';
    const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.dataset.accountMotion === 'reduced';
    const announce = text => { $('aiAnnouncer').textContent = text; };
    const element = (tag, cls, text) => {const node = document.createElement(tag);if (cls) node.className = cls;if (text !== undefined) node.textContent = String(text);return node;};
    function icon(name) {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        for (const [k,v] of Object.entries({class:'account-icon',width:20,height:20,viewBox:'0 0 24 24',fill:'none',stroke:'currentColor','stroke-width':1.7,'aria-hidden':'true'})) svg.setAttribute(k,v);
        const use = document.createElementNS(svg.namespaceURI,'use');use.setAttribute('href',document.body.dataset.iconUrl + '#' + name);svg.append(use);return svg;
    }
    function button(label, action, cls = '', symbol) {
        const node = element('button',cls);node.type = 'button';if (symbol) node.append(icon(symbol));node.append(document.createTextNode(label));node.addEventListener('click',action);return node;
    }
    function link(label, url, cls = '') {
        const node = element('a',cls,label);
        try {const parsed = new URL(url,location.origin);if (['http:','https:'].includes(parsed.protocol)) {node.href = parsed.href;if (parsed.origin !== location.origin) {node.target = '_blank';node.rel = 'noopener noreferrer';}}} catch (_) {}
        return node;
    }
    function hotelListText(text, components) {
        const names = [...new Set(components.filter(c => c.type === 'hotels').flatMap(c => c.items || []).map(h => h.name).filter(Boolean))];
        if (names.length < 2) return text;
        const choices = names.sort((a,b) => b.length-a.length).map(name => name.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')).join('|');
        return text.replace(new RegExp('(?:'+choices+')(?:\\s*[,;]\\s*(?:'+choices+'))+(?:\\.)?','gu'), list =>
            '\n\n' + list.match(new RegExp(choices,'gu')).map(name => '- ' + name).join('\n') + '\n\n');
    }
    async function api(path, method = 'GET', data, key, options = {}) {
        if (!token()) {const e = Error('Đăng nhập để trò chuyện và lưu lịch trình của bạn.');e.login = true;throw e;}
        const controller = options.controller || new AbortController(), timeout = setTimeout(() => controller.abort('timeout'), options.timeoutMs || 300000);
        try {
            const response = await fetch('/api' + path,{method,signal:controller.signal,headers:{Accept:options.onProgress ? 'text/event-stream' : 'application/json',Authorization:'Bearer ' + token(),
                ...(options.requestId ? {'X-Aurel-Request-Id':options.requestId} : {}),...(data ? {'Content-Type':'application/json'} : {}),...(key ? {'Idempotency-Key':key} : {})},...(data ? {body:JSON.stringify(data)} : {})});
            if(response.ok && options.onProgress && response.headers.get('Content-Type')?.includes('text/event-stream'))return await readProgressStream(response,options);
            const body = await response.json().catch(() => ({}));
            if (!response.ok || body.code) {
                const message = response.status === 401 ? 'Phiên đăng nhập đã hết hạn. Bạn đăng nhập lại để tiếp tục nhé.'
                    : response.status === 429 ? 'Bạn đang gửi yêu cầu hơi nhanh. Chờ một chút rồi thử lại nhé.'
                    : response.status >= 500 && !body.code ? 'Máy chủ chưa xử lý được yêu cầu. Bạn thử lại sau một chút nhé.'
                    : body.message || 'Chưa tải được phản hồi. Bạn thử lại nhé.';
                const error = Error(message);error.status=response.status;error.login = response.status === 401;throw error;
            }
            return body;
        } catch (error) {
            if(controller.signal.aborted)throw Error(controller.signal.reason === 'timeout'
                ? 'Yêu cầu mất nhiều thời gian hơn dự kiến. Bạn có thể thử lại; hệ thống sẽ tránh tạo lịch trùng.'
                : 'Đã ngắt chờ phản hồi. Bạn có thể xem lại cuộc trò chuyện trước khi thử lại.');
            if(error instanceof TypeError)throw Error('Kết nối phản hồi đã ngắt. Bạn kiểm tra mạng rồi thử lại nhé; hệ thống không tự gửi lại tác vụ.');
            throw error;
        }
        finally {clearTimeout(timeout);}
    }
    async function readProgressStream(response, options) {
        if(!response.body?.getReader)throw Error('Trình duyệt chưa hỗ trợ nhận tiến trình. Bạn thử tải lại trang nhé.');
        const reader = response.body.getReader(), decoder = new TextDecoder();let buffer = '', lastSequence = 0;
        try {
            while(true) {
                const chunk = await reader.read();buffer += decoder.decode(chunk.value || new Uint8Array(),{stream:!chunk.done});
                if(buffer.length > 2097152)throw Error('Phản hồi chưa đúng định dạng. Bạn thử lại nhé.');
                let delimiter;
                while((delimiter = /\r?\n\r?\n/.exec(buffer))) {
                    const frame = buffer.slice(0,delimiter.index);buffer = buffer.slice(delimiter.index + delimiter[0].length);
                    const lines = frame.split(/\r?\n/), event = lines.find(line => line.startsWith('event:'))?.slice(6).trim();
                    const raw = lines.filter(line => line.startsWith('data:')).map(line => line.slice(5).trimStart()).join('\n');if(!raw)continue;
                    let payload;try{payload = JSON.parse(raw);}catch(_){throw Error('Phản hồi chưa đúng định dạng. Bạn thử lại nhé.');}
                    if(payload.request_id !== options.requestId || !Number.isInteger(payload.sequence) || payload.sequence <= lastSequence)continue;
                    lastSequence = payload.sequence;
                    if(event === 'progress')options.onProgress(payload);
                    else if(event === 'result') {
                        if(!payload.result || typeof payload.result.message !== 'string')throw Error('Phản hồi chưa đầy đủ. Bạn thử lại nhé.');
                        return payload.result;
                    } else if(event === 'error') {
                        const error = Error(payload.message || 'Chưa xử lý được yêu cầu. Bạn thử lại nhé.');error.retryable = payload.retryable !== false;throw error;
                    }
                }
                if(chunk.done)throw Error('Kết nối phản hồi đã ngắt. Hệ thống không tự gửi lại tác vụ; bạn có thể thử lại bằng nút bên dưới.');
            }
        } finally {reader.cancel().catch(() => {});reader.releaseLock();}
    }
    function renderHistory() {
        const list = $('aiHistoryList');list.replaceChildren();
        if (historyError) {const box = element('div','ai-history-empty',historyError);if (token()) box.append(button('Thử lại',loadHistory));list.append(box);}
        const items = [...threads].filter(t => t.serverId).reverse();
        if (!items.length && !historyError) list.append(element('div','ai-history-empty',token() ? 'Chưa có cuộc trò chuyện. Bắt đầu từ ý tưởng của bạn.' : 'Đăng nhập để xem lịch sử trò chuyện.'));
        for (const thread of items) {
            const row = element('div','ai-history-row'), open = button('',() => selectThread(thread.id),'ai-history-thread');row.dataset.conversationId = thread.id;open.setAttribute('aria-current',String(thread.id === activeId));
            open.append(icon('message-square-text'),element('span','',thread.title));open.title = thread.title;
            const close = button('',() => deleteThread(thread),'ai-thread-close','x');close.setAttribute('aria-label','Xóa hội thoại: '+thread.title);close.title = 'Xóa hội thoại';close.disabled = thread.deleting || jobs.has(thread.id);
            row.append(open,close);list.append(row);
        }
        const thread=current();$('aiCloseConversation').hidden=!thread?.serverId;
        $('aiCloseConversation').disabled=!!thread?.deleting || jobs.has(thread?.id);
    }
    function sessionNotice(text,error=false) {
        const notice=$('aiSessionNotice');notice.textContent=text;notice.hidden=!text;notice.dataset.tone=error?'error':'success';
    }
    async function deleteThread(thread) {
        if(thread.deleting || jobs.has(thread.id))return;
        thread.deleting=true;renderHistory();resizeInput();sessionNotice('Đang xóa hội thoại…');
        try {
            try{if(thread.serverId)await api('/chat/'+thread.serverId,'DELETE',null,null,{timeoutMs:15000});}catch(error){if(error.status!==404)throw error;}
            deletedSessions.add(thread.serverId);
            threads.splice(threads.indexOf(thread),1);thread.messages=[];thread.draft='';
            if(activeId===thread.id)newThread();else renderHistory();
            sessionNotice('Hội thoại đã xóa');
        }catch(error){thread.deleting=false;renderHistory();resizeInput();sessionNotice(error.message || 'Chưa xóa được hội thoại. Bạn thử lại nhé.',true);}
    }
    function toggleHistory(open, restore = true) {
        app.dataset.historyOpen = String(open);history.inert = !open;history.setAttribute('aria-hidden',String(!open));
        $('aiToggleHistory').hidden = open;$('aiToggleHistory').setAttribute('aria-expanded',String(open));$('aiCloseHistory').setAttribute('aria-expanded',String(open));
        $('aiHistoryBackdrop').hidden = !open || !mobile.matches;workspace.inert = open && mobile.matches;
        if (open && mobile.matches) {history.setAttribute('role','dialog');history.setAttribute('aria-modal','true');}
        else {history.removeAttribute('role');history.removeAttribute('aria-modal');}
        if (open && (mobile.matches || restore)) requestAnimationFrame(() => {if(app.dataset.historyOpen === 'true') $('aiCloseHistory').focus({preventScroll:true});});
        else if (!open && restore) $('aiToggleHistory').focus({preventScroll:true});
    }
    async function selectThread(id) {
        const selected=threads.find(t=>t.id===id);if(!selected || selected.deleting)return;
        sessionNotice('');
        if (current()) current().draft = input.value;activeId = id;const thread = current();input.value = thread.draft || '';
        if (mobile.matches) toggleHistory(false,false);renderHistory();renderConversation(true);
        if (thread.loaded || !thread.serverId || jobs.has(thread.id)) return;
        jobs.set(thread.id,true);renderHistory();renderConversation();
        try {
            const session = await api('/chat/' + thread.serverId);
            thread.messages = session.messages.map(m => ({id:'stored-' + m.id,serverMessageId:m.id,role:m.role === 'bot' ? 'assistant' : 'user',text:m.content,assistant:m.entities_json?.assistant}));thread.loaded = true;thread.closed = session.status !== 'active';
        } catch (error) {thread.messages = [{id:uid(),role:'assistant',error:error.message,login:error.login,historyRetry:true}];}
        finally {jobs.delete(thread.id);renderHistory();if(activeId === thread.id) renderConversation(true);}
    }
    async function loadHistory() {
        if (!token()) return;historyError = 'Đang tải lịch sử…';renderHistory();
        try {
            const sessions = await api('/chat');historyError = '';
            for (const s of [...sessions].reverse()) if (!deletedSessions.has(s.id) && !threads.some(t => t.serverId === s.id)) threads.push({id:uid(),serverId:s.id,title:s.title,messages:[],draft:'',loaded:false,closed:s.status!=='active'});
        } catch (error) {historyError = error.message;}
        renderHistory();
    }
    function newThread(focus = true) {
        sessionNotice('');
        if(current())current().draft = input.value;
        let thread = threads.find(t => !t.closed && !t.serverId && !t.messages.length && !jobs.has(t.id));
        if(!thread) {thread = {id:uid(),title:'Cuộc trò chuyện mới',messages:[],draft:'',loaded:true,startKey:uid()};threads.push(thread);}
        activeId = thread.id;input.value = thread.draft;renderHistory();renderConversation(true);if(mobile.matches)toggleHistory(false,false);if(focus)input.focus({preventScroll:true});
    }
    function resizeInput() {
        input.style.height = 'auto';input.style.height = Math.min(input.scrollHeight,180) + 'px';
        $('aiInputCount').hidden = input.value.length < 1600;$('aiInputCount').textContent = input.value.length + '/2000';
        $('aiSend').disabled = !input.value.trim() || jobs.has(activeId) || !!current()?.closed || !!current()?.deleting;
        app.style.setProperty('--composer-height',$('aiComposerDock').offsetHeight + 'px');
    }
    const nearBottom = () => scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < 120;
    function toBottom(smooth = true) {requestAnimationFrame(() => scroll.scrollTo({top:scroll.scrollHeight,behavior:smooth && !reduced() ? 'smooth' : 'instant'}));$('aiJump').hidden = true;}
    function panel(title, eyebrow, symbol, subtitle) {
        const box = element('section','ai-result-panel'), head = element('div','ai-panel-heading'), label = element('p','ai-panel-eyebrow');
        label.append(icon(symbol),document.createTextNode(customerText(eyebrow)));head.append(label,element('h2','',customerText(title)));if(subtitle)head.append(element('p','ai-panel-subtitle',customerText(subtitle)));box.append(head);return box;
    }
    function cards(container, component) {
        const row = element('div','ai-result-grid');row.dataset.component = component.type;
        const hasPhotos=component.items.some(item=>item.image?.url);
        for(const item of component.items) {
            let visitDateInput=null,hoursRow=null;const visitWarning=element('div','ai-hours-warning');
            const card = element('article','ai-result-card'), mark = element('span','ai-card-symbol'), heading=element('div','ai-card-heading');
            if(hasPhotos){const media=element('div','ai-card-media');if(!photo(media,item.image)){media.classList.add('ai-card-media-empty');media.append(element('span','ai-panel-subtitle','Không có ảnh'));}card.append(media);}
            mark.append(icon(component.type === 'hotels' ? 'hotel' : 'pin'));heading.append(mark,element('h3','',customerText(item.name)));card.append(heading);
            if(item.address)card.append(element('p','',customerText(item.address)));if(item.description)card.append(element('p','',customerText(window.AurelPlanner?.description(item.description) ?? item.description)));
            if(component.type==='places')ticketInfo(card,item.ticket,item.ticket_estimate);
            if(item.provider==='google_sheet') {
                if(item.area)card.append(element('p','ai-panel-subtitle','Khu vực: '+item.area));
                if(item.phone)card.append(element('p','', 'Điện thoại công khai: '+item.phone));
                const visitLabel=element('label','ai-panel-subtitle','Ngày ghé '),visit=element('input','ai-visit-date');visitDateInput=visit;visit.type='date';visit.value=item.opening_check?.visit_date || window.AurelPlanner?.visitDate() || '';visitLabel.append(visit);card.append(visitLabel);
                visitLabel.remove();
                const opening=element('div','ai-place-hours');hoursRow=opening;
                const showHours=check=>window.AngelOpeningHours.render(opening,check?.display || (item.hours_text?{has_data:true,status:'unknown',summary:'Giờ hoạt động: '+item.hours_text,hours_text:item.hours_text}:null),check,{extra:visitLabel,warningTarget:visitWarning});
                showHours(item.opening_check);
                let checkSequence=0;
                const updateHours=async()=>{
                    const sequence=++checkSequence,date=visit.value;
                    try{const id=item.source_place_id || Number(String(item.id).replace(/^sheet:/,''));const result=await window.AurelPlanner.hoursForPlace(id,date);
                        if(sequence!==checkSequence || !card.isConnected)return;item.opening_check=result.opening_check;showHours(result.opening_check);
                    }catch(error){if(sequence===checkSequence){const check=item.opening_check;showHours({...check,display:check?.display?.has_data?{...check.display,status:'unknown',summary:'Giờ hoạt động: Chưa cập nhật được trạng thái hiện tại'}:null});}}
                };
                opening.refreshHours=updateHours;
                visit.addEventListener('change',updateHours);
                queueMicrotask(updateHours);
                const price=item.price_reference;
                if(price && (price.min != null || price.max != null)) {
                    const range=price.min==null?'Đến '+money(price.max):price.max==null || price.min===price.max?money(price.min):money(price.min)+' – '+money(price.max);
                    card.append(element('p','', 'Giá tham khảo: '+range+(price.unit?' · '+price.unit:'')));
                }
                if(item.maps_link_type)card.append(element('small','ai-panel-subtitle',item.maps_link_type));
            }
            if(component.type==='places' && item.provider!=='google_sheet'){const opening=element('div','ai-place-hours');window.AngelOpeningHours.render(opening,item.opening_check?.display,item.opening_check,{warningTarget:visitWarning});card.append(opening);}
            if(hoursRow)card.append(hoursRow);
            const actions=element('div','ai-card-actions');
            if(component.type === 'destinations')actions.append(button('Khám phá',() => send('Ở ' + item.name + ' có gì để khám phá?'),'ai-small-button'));
            else if(item.url || item.source_url || component.type === 'places') {
                const target=component.type === 'hotels' ? item.url || item.source_url : window.AurelPlanner.mapUrl(item);
                if(target)actions.append(link(component.type === 'hotels' ? 'Xem khách sạn' : item.provider==='google_sheet' ? 'Truy cập địa điểm' : 'Xem trên Google Maps',target,'ai-small-button ai-source-link'));
                if(item.provider==='google_sheet' && window.AurelPlanner?.canAddPlace())actions.append(button('Thêm vào lịch',()=>window.AurelPlanner.addPlace(item,visitDateInput?.value),'ai-small-button ai-add-place'));
            }
            if(item.website)actions.append(link('Website tham khảo',item.website,'ai-small-button ai-source-link'));
            if(actions.childElementCount)card.append(actions);
            if(component.type==='places')card.append(visitWarning);
            const source=component.type === 'hotels'?'Chọn ngày lưu trú để xem giá và phòng phù hợp.':customerText(item.source || '');
            if(source)card.append(element('small','ai-card-source',source));row.append(card);
        }container.append(row);
    }
    function photo(container, data) {
        return window.AurelPlacePhotos?.render(container,data,data?.alt);
    }
    function budget(container, data) {
        data ||= {};
        const box = panel(data.limit==null ? (data.flexible?'Ngân sách linh hoạt':'Bạn dự trù bao nhiêu cho chuyến đi?') : 'Ngân sách chuyến đi','CÂN ĐỐI CHI TIÊU','wallet');box.dataset.component = 'budget';
        if(data.limit!=null){const total = element('div','ai-budget-total');total.append(element('strong','',money(data.limit)));box.append(total);}
        else if(!data.flexible){const choices=element('div','ai-quick-replies');for(const choice of ['Khoảng 2 triệu','Khoảng 5 triệu','Chưa chốt, cứ lên lịch trước'])choices.append(button(choice,()=>send(choice)));box.append(choices);}
        if(Number.isFinite(data.ticket_total) && (data.ticket_known_places>0 || data.ticket_total>0)) {
            const line=element('div','ai-budget-line');line.append(element('span','','Vé tham quan có giá tham khảo'),element('strong','',money(data.ticket_total)));box.append(line);
        }
        if(data.unpriced_places)box.append(element('p','ai-panel-subtitle',data.unpriced_places+' điểm cần chốt thông tin vé; xem nguồn ở từng điểm.'));
        if(data.children_needing_height)box.append(element('p','ai-panel-subtitle','Phần vé trẻ em cần đối chiếu thêm chiều cao hoặc điều kiện đi cùng người lớn.'));
        for(const warning of data.warnings || [])box.append(element('p',data.status==='over_budget'?'ai-budget-warning':'ai-panel-subtitle',customerText(warning)));
        box.append(element('p','ai-panel-subtitle',data.flexible?'Bạn có thể bổ sung ngân sách sau. Mình đã tách thông tin vé ở từng điểm để bạn dễ cân đối.':'Ăn uống, chỗ ở và di chuyển chưa có giá thì chưa được cộng vào tổng tạm tính.'));container.append(box);
    }
    function ticketInfo(container,ticket,estimate) {
        if(!ticket)return;
        const box=element('div','ai-ticket');box.append(element('strong','',ticket.label));
        for(const tier of ticket.tiers || [])box.append(element('p','',(tier.amount===0?'Miễn phí':money(tier.amount)+'/người')+' · '+tier.label));
        if(ticket.note)box.append(element('small','',ticket.note));
        if(estimate?.amount>0)box.append(element('p','ai-ticket-group','Dự tính phần vé của nhóm: '+money(estimate.amount)+(estimate.unknown_children?' · phần vé trẻ em cần đối chiếu thêm':'')));
        if(ticket.source_url)box.append(link('Nguồn giá · kiểm tra '+String(ticket.checked_at || '').split('-').reverse().join('/'),ticket.source_url));container.append(box);
    }
    function disposeMaps(container=$('aiMessages')) {
        for(const item of maps)if(container.contains(item.box)){item.controller.abort('cancelled');item.map?.remove();maps.delete(item);}
    }
    async function routeMap(container, day, message, thread, data) {
        const stops = day.items.filter(i => Array.isArray(i.coordinates));
        const box = panel('Di chuyển trong ngày '+day.day_number,'BẢN ĐỒ HÀNH TRÌNH','route','Các điểm đã chọn và tuyến đường theo phương tiện của bạn.');box.dataset.component = 'route';container.append(box);
        if(!stops.length){
            box.append(element('p','ai-panel-subtitle','Các địa điểm chưa có tọa độ để vẽ tuyến đường. Bạn có thể kiểm tra vị trí bằng liên kết Google Maps của từng điểm.'));
            for(const stop of day.items){const target=window.AurelPlanner?.mapUrl(stop);if(stop.kind!=='break' && target)box.append(link(customerText(stop.title),target,'ai-small-button'));}
            return;
        }
        if(!window.L){box.append(element('p','ai-panel-subtitle','Bản đồ đang không tải được. Bạn có thể thử mở lại sau.'));return;}
        const points=stops.map(p=>[p.coordinates[1],p.coordinates[0]]),canvas=element('div','ai-map-canvas');canvas.setAttribute('aria-label','Bản đồ các điểm tham quan ngày '+day.day_number);box.append(canvas);
        const map=window.L.map(canvas,{scrollWheelZoom:false,zoomControl:false});window.L.control.zoom({zoomInTitle:'Phóng to',zoomOutTitle:'Thu nhỏ'}).addTo(map);
        const item={box,map,controller:new AbortController()};maps.add(item);
        const status=element('p','ai-map-status','Đang tải bản đồ và tuyến đường…');status.setAttribute('role','status');box.append(status);
        let tilesFailed=false,routePending=true;
        const updateStatus=()=>{if(box.isConnected)status.textContent=tilesFailed?'Nền bản đồ đang tải chậm. Bạn có thể dùng nút mở chỉ đường bên dưới.':routePending?'Đang tải tuyến đường…':'Thời gian di chuyển là ước tính theo phương tiện đã chọn.';};
        window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'})
            .on('tileerror',()=>{tilesFailed=true;updateStatus();}).addTo(map);
        stops.forEach((stop,index)=>{
            const popup=element('div','ai-map-popup');popup.append(element('strong','',customerText(stop.title)));photo(popup,stop.image);if(stop.address)popup.append(element('p','',customerText(stop.address)));
            window.L.marker(points[index],{icon:window.L.divIcon({className:'ai-map-marker',html:'<span>'+(index+1)+'</span>',iconSize:[30,30],iconAnchor:[15,15]})}).bindPopup(popup).addTo(map);
            const line=element('p','ai-panel-subtitle',(index+1)+'. '+customerText(stop.title));if(stop.travel_from_previous?.time_seconds != null && stop.travel_from_previous?.distance_metres != null)line.append(document.createTextNode(' · '+Math.ceil(stop.travel_from_previous.time_seconds/60)+' phút · '+(stop.travel_from_previous.distance_metres/1000).toFixed(1)+' km'));box.append(line);
        });
        map.fitBounds(window.L.latLngBounds(points),{padding:[30,30],maxZoom:14});requestAnimationFrame(()=>{if(box.isConnected)map.invalidateSize();});
        const mode=data.transport || stops.find(s=>s.travel_from_previous?.mode)?.travel_from_previous.mode || 'drive';
        const query=new URLSearchParams({api:'1',origin:points[0].join(','),destination:points.at(-1).join(','),travelmode:{walk:'walking',drive:'driving',bicycle:'bicycling'}[mode]});if(points.length>2)query.set('waypoints',points.slice(1,-1).map(p=>p.join(',')).join('|'));
        box.append(link('Mở chỉ đường','https://www.google.com/maps/dir/?'+query,'ai-small-button'));
        try {
            if(!message.serverMessageId || !thread.serverId)throw Error('Mở lại cuộc trò chuyện để tải tuyến đường của lịch này.');
            const route=await api('/chat/'+thread.serverId+'/messages/'+message.serverMessageId+'/route/'+day.day_number,'GET',null,null,{controller:item.controller,timeoutMs:30000});
            if(!box.isConnected || item.controller.signal.aborted)return;
            if(route.geometry)window.L.geoJSON(route.geometry,{style:{color:'#2563EB',weight:4,opacity:.9}}).addTo(map);
            routePending=false;updateStatus();
        }catch(error){if(box.isConnected && item.controller.signal.reason!=='cancelled')status.textContent=item.controller.signal.reason==='timeout'?'Tuyến đường đang phản hồi chậm. Bạn có thể mở chỉ đường hoặc đóng rồi mở lại bản đồ.':error.message || 'Tuyến đường chưa tải được. Bạn vẫn có thể xem vị trí hoặc mở chỉ đường.';}
    }
    function timeline(container, data, message, thread) {
        const box = panel(data.title,'LỊCH TRÌNH CỦA BẠN','calendar-days',data.provisional ? 'Lịch sơ bộ · chưa chốt ngày khởi hành' : 'Đã lưu trong chuyến đi của bạn');box.dataset.component = 'timeline';
        if(data.planning_area?.notice)box.append(element('p','ai-panel-subtitle',customerText(data.planning_area.notice)));
        const tabs = element('div','ai-day-tabs');tabs.setAttribute('role','tablist');tabs.setAttribute('aria-label','Ngày trong lịch trình');
        const content = element('div','ai-timeline');content.id = 'day-' + message.id;content.setAttribute('role','tabpanel');let selected = message.day || 0;
        const render = () => {
            message.day = selected;disposeMaps(content);content.replaceChildren();content.setAttribute('aria-labelledby',message.id + '-tab-' + selected);
            [...tabs.children].forEach((t,i) => {t.setAttribute('aria-selected',String(i === selected));t.tabIndex = i === selected ? 0 : -1;});
            const day = data.days[selected];
            for(const item of day.items) {
                const stop = element('div','ai-timeline-stop'), detail = element('div','ai-stop-detail');detail.style.setProperty('--stop-color',item.kind === 'break' ? '#D97706' : '#3B82F6');
                detail.append(element('h3','',customerText(item.title)));photo(detail,item.image);detail.append(element('p','',customerText(window.AurelPlanner?.description(item.description) ?? item.description)));
                if(item.address)detail.append(element('small','',customerText(item.address)));
                if(item.travel_from_previous){const travel=item.travel_from_previous;
                    detail.append(element('p','',travel.status==='unverified'
                        ? 'Tạm chừa '+travel.scheduled_minutes+' phút chuyển điểm · chưa xác minh thời gian và quãng đường thực tế'
                        : Math.ceil(travel.time_seconds / 60) + ' phút theo tuyến Geoapify · ' + (travel.distance_metres / 1000).toFixed(1) + ' km' + (travel.scheduled_minutes?' · chừa '+travel.scheduled_minutes+' phút chuyển điểm (gồm thời gian dự phòng)':'')));}
                ticketInfo(detail,item.ticket,item.ticket_estimate);
                if(item.kind!=='break' && !item.ticket)detail.append(element('small','ai-ticket-unknown','Bạn nên kiểm tra thông tin vé với địa điểm trước khi đi.'));
                const visitWarning=element('div','ai-hours-warning');
                if(item.kind!=='break'){const opening=element('div','ai-place-hours');window.AngelOpeningHours.render(opening,item.opening_display || item.opening_check?.display,item.opening_check,{warningTarget:visitWarning});detail.append(opening);}
                if(item.kind !== 'break' && window.AurelPlanner?.mapUrl(item))detail.append(link('Xem trên Google Maps',window.AurelPlanner.mapUrl(item),'ai-map-link'));
                detail.append(visitWarning);
                const marker = element('span','ai-timeline-marker');marker.setAttribute('aria-hidden','true');
                stop.append(element('time','',item.start_time + '–' + item.end_time),marker,detail);content.append(stop);
            }
            content.append(button('Xem bản đồ ngày ' + (selected + 1),() => {const old = content.querySelector('[data-component="route"]');if(old){disposeMaps(content);old.remove();}else routeMap(content,day,message,thread,data);},'ai-small-button','route'));
        };
        data.days.forEach((day,i) => {const tab = button('Ngày ' + day.day_number + (day.date ? ' · ' + day.date : ''),() => {selected = i;render();});tab.id = message.id + '-tab-' + i;tab.setAttribute('role','tab');tab.setAttribute('aria-controls',content.id);
            tab.addEventListener('keydown',e => {if(!['ArrowLeft','ArrowRight','Home','End'].includes(e.key))return;e.preventDefault();selected = e.key === 'Home' ? 0 : e.key === 'End' ? data.days.length - 1 : (selected + (e.key === 'ArrowRight' ? 1 : -1) + data.days.length) % data.days.length;render();tabs.children[selected].focus();});tabs.append(tab);});
        box.append(tabs,content);render();
        const notes=(data.assumptions || []).filter(note=>!/MySQL|ngân sách tổng|Chưa đặt phòng|chưa tạo chuyến đi|Tạm coi nơi ở/iu.test(note));
        if(notes.length){const footer=element('details','ai-panel-footer ai-trip-settings');footer.append(element('summary','','Thông tin tạm tính · có thể điều chỉnh'));for(const note of notes)footer.append(element('p','',customerText(note)));box.append(footer);}
        container.append(box);budget(container,data.budget);
    }
    function renderMessage(message, thread) {
        const article = element('article','ai-message ai-message-' + message.role);article.dataset.messageId = message.id;
        if(message.role === 'user') {article.append(element('div','ai-user-bubble',message.text));return article;}
        const heading = element('div','ai-assistant-heading'), mark = element('span','ai-assistant-mark');mark.append(icon('star'));heading.append(mark,document.createTextNode('Trợ lý du lịch'));
        const body = element('div','ai-response-body');article.append(heading,body);
        if(message.loading) {const loading = element('div','ai-loading');loading.setAttribute('aria-live','off');const dots = element('span','ai-loading-dots');dots.setAttribute('aria-hidden','true');for(let i=0;i<3;i++)dots.append(element('i'));loading.append(dots,element('span','ai-loading-text',progressText(message.progress)));body.append(loading);return article;}
        if(message.error) {const error = element('div','ai-error');error.setAttribute('role','alert');error.append(element('strong','','Chưa thể trả lời'),element('p','',customerText(message.error)));
            if(message.login)error.append(link('Đăng nhập','/login','ai-small-button'));
            else if(message.retryable !== false)error.append(button('Thử lại',() => message.historyRetry ? selectThread(thread.id) : respond(thread,message),'ai-small-button','refresh'));body.append(error);return article;}
        const assistant=message.assistant || {}, question=customerText(assistant.question).trim();
        let removedClosedPlace=false;
        const components=(assistant.components || []).flatMap(component=>{
            const excluded = item => window.AurelPlanner?.excluded(item,assistant.state?.destination_name || assistant.state?.location_id);
            if(component.type==='places') {
                const items=(component.items || []).filter(item=>!excluded(item));
                if(items.length!==(component.items || []).length)removedClosedPlace=true;
                if(!items.length)return [];
                component={...component,items};
            }
            if(component.type==='timeline' && component.days.some(day=>day.items.some(excluded))) {removedClosedPlace=true;return [];}
            if(component.type!=='destinations' || assistant.state?.location_id==null || component.items.length!==1)return [component];
            return String(component.items[0].id)===String(assistant.state.location_id)?[]:[component];
        });
        const hasVisual=components.some(c=>['places','hotels','destinations'].includes(c.type)?c.items?.length:c.type==='timeline'||c.type==='budget');
        let introduction=customerText(message.text).trim();
        if(removedClosedPlace)introduction='Gợi ý cũ có địa điểm đã được báo đóng cửa nên không còn được đề xuất. Bạn hãy gửi yêu cầu mới để AI chọn địa điểm khác.';
        if(question){
            // The API and old history include the question in message as well as assistant.question.
            // Extract only that known question, including whitespace variants, without guessing other sentences.
            const escaped=question.split(/\s+/).map(word=>word.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')).join('\\s+');
            introduction=introduction.replace(new RegExp(escaped,'giu'),'').trim();
        }
        introduction=hotelListText(introduction,components);
        const appendText=(value,className='ai-answer-text')=>{if(!value)return;const text=element('div',className);for(const paragraph of value.split(/\n\s*\n/)) {
            const lines=paragraph.trim().split('\n').map(line=>line.trim()).filter(Boolean);
            if(lines.length && lines.every(line=>/^(?:[-*•]|\d+[.)])\s+/.test(line))) {
                const list=element(/^\d/.test(lines[0])?'ol':'ul');for(const line of lines)list.append(element('li','',line.replace(/^(?:[-*•]|\d+[.)])\s+/,'')));text.append(list);
            } else if(paragraph.trim())text.append(element('p','',paragraph));
        }body.append(text);};
        appendText(hasVisual?introduction:[introduction,question].filter(Boolean).join('\n\n'));
        for(const c of components) {
            if(['places','hotels','destinations'].includes(c.type))cards(body,c);else if(c.type === 'timeline')timeline(body,c,message,thread);else if(c.type === 'budget')budget(body,c);else if(c.type === 'notice')for(const note of c.items)body.append(element('p','ai-panel-subtitle',customerText(note)));
            if(c.type === 'timeline' && window.AurelPlanner) body.append(window.AurelPlanner.proposal(c,message,assistant.state));
        }
        if(hasVisual && question)appendText(question,'ai-answer-text ai-follow-up');
        if(message.assistant?.quick_replies?.length) {const replies = element('div','ai-quick-replies');for(const reply of message.assistant.quick_replies){const label=customerText(reply);if(label)replies.append(button(label,() => send(label),'','plus'));}body.append(replies);}
        return article;
    }
    function renderConversation(force = false, anchor) {
        const thread = current(), hasMessages = thread.messages.length > 0, follow = force || nearBottom();
        app.dataset.mode = hasMessages ? 'chat' : 'welcome';app.dataset.conversationId = thread.id;input.rows = hasMessages ? 1 : 2;
        $('aiWelcome').hidden = hasMessages;$('aiMessages').hidden = !hasMessages;
        const slot = hasMessages ? $('aiComposerDock') : $('aiWelcomeComposerSlot');if(composer.parentElement !== slot)slot.append(composer,$('aiComposerDisclaimer'));$('aiComposerDock').hidden = !hasMessages;
        disposeMaps();$('aiMessages').replaceChildren(...thread.messages.map(m => renderMessage(m,thread)));$('aiMessages').setAttribute('aria-busy',String(jobs.has(activeId)));
        if(hasMessages && follow) {
            const answer = anchor ? [...$('aiMessages').children].find(n => n.dataset.messageId === anchor) : null;
            if(answer && answer.offsetHeight > scroll.clientHeight - 48)requestAnimationFrame(() => scroll.scrollTo({top:scroll.scrollTop + answer.getBoundingClientRect().top - scroll.getBoundingClientRect().top - 16,behavior:reduced() ? 'instant' : 'smooth'}));else toBottom(!force);
        }
        if(!hasMessages){scroll.scrollTop = 0;$('aiJump').hidden = true;}resizeInput();
    }
    async function respond(thread, reply) {
        if(jobs.has(thread.id))return;
        const job = {controller:new AbortController(),requestId:uid(),slowTimer:null,totalTimer:null};jobs.set(thread.id,job);
        renderHistory();
        reply.loading = true;reply.error = null;reply.retryable = true;reply.progress = {code:'considering'};
        const update = state => {
            if(jobs.get(thread.id) !== job || !reply.loading || job.controller.signal.aborted)return false;
            reply.progress = state;
            if(activeId === thread.id) {
                const article = [...$('aiMessages').children].find(node => node.dataset.messageId === reply.id), status = article?.querySelector('.ai-loading-text');
                const text = progressText(state);if(status && status.textContent !== text){status.textContent = text;announce(text);}
            }
            return true;
        };
        const waitNotice = () => {clearTimeout(job.slowTimer);job.slowTimer = setTimeout(() => update({code:'waiting'}),20000);};
        job.totalTimer = setTimeout(() => job.controller.abort('timeout'),300000);waitNotice();
        if(activeId === thread.id){renderConversation();announce(PROGRESS_MESSAGES.considering);}
        try {
            if(!thread.serverId) {const started = await api('/chat/start','POST',{},thread.startKey,{controller:job.controller});thread.serverId = started.session_id;}
            const result = await api('/chat/' + thread.serverId + '/message','POST',{message:reply.prompt,...(reply.plannerContext ? {planner_context:reply.plannerContext} : {})},reply.requestKey,
                {controller:job.controller,requestId:job.requestId,onProgress:state => {if(update(state))waitNotice();}});
            reply.text = result.message;reply.assistant = result.assistant;reply.serverMessageId=result.message_id;reply.loading = false;reply.error = null;
        } catch(error) {reply.error = error.message || 'Chưa kết nối được máy chủ. Bạn thử lại nhé.';reply.login = error.login;reply.retryable = error.retryable !== false;reply.loading = false;}
        finally {
            clearTimeout(job.slowTimer);clearTimeout(job.totalTimer);delete reply.progress;
            if(jobs.get(thread.id) === job)jobs.delete(thread.id);
            renderHistory();if(activeId === thread.id){renderConversation(false,reply.id);announce(reply.error || 'Phản hồi đã sẵn sàng.');}
        }
    }
    function send(text) {
        const value = String(text || '').trim(), thread = current();if(!value || jobs.has(thread.id))return;
        if(window.AurelPlanner && !window.AurelPlanner.context()){sessionNotice('Lịch này đang ở chế độ xem. Mở một lịch có thể chỉnh sửa để nhận đề xuất điều chỉnh.');return;}
        if(thread.closed || thread.deleting){announce('Cuộc trò chuyện không thể gửi thêm tin. Hãy tạo cuộc trò chuyện mới.');return;}
        sessionNotice('');
        if(value.length > 2000){announce('Tin nhắn tối đa 2.000 ký tự.');return;}
        if(thread.messages.at(-1)?.error && !thread.messages.at(-1).login){announce('Hãy thử lại tin nhắn trước hoặc bắt đầu cuộc trò chuyện mới.');return;}
        if(!thread.messages.length)thread.title = value.slice(0,60);
        thread.messages.push({id:uid(),role:'user',text:value});const reply = {id:uid(),role:'assistant',prompt:value,requestKey:uid(),loading:true};thread.messages.push(reply);
        if(window.AurelPlanner) {reply.plannerContext=window.AurelPlanner.context();reply.plannerVersion=window.AurelPlanner.version();}
        input.value = '';thread.draft = '';renderHistory();renderConversation(true);respond(thread,reply);input.focus({preventScroll:true});
    }
    composer.addEventListener('submit',e => {e.preventDefault();send(input.value);});input.addEventListener('input',() => {current().draft = input.value;resizeInput();});
    input.addEventListener('compositionstart',() => {composing = true;});input.addEventListener('compositionend',() => {composing = false;});
    input.addEventListener('keydown',e => {if(e.key === 'Enter' && !e.shiftKey && !e.isComposing && !composing && e.keyCode !== 229){e.preventDefault();if(!jobs.has(activeId))composer.requestSubmit();}});
    app.querySelectorAll('[data-ai-new]').forEach(n => n.addEventListener('click',() => newThread()));
    $('aiCloseConversation').addEventListener('click',()=>{if(current())deleteThread(current());});
    app.querySelectorAll('[data-ai-example]').forEach(n => n.addEventListener('click',() => {$('aiExamples').open = false;send(window.AurelPlanner?.example(n.dataset.aiExample));}));
    $('aiToggleHistory').addEventListener('click',() => toggleHistory(app.dataset.historyOpen !== 'true'));$('aiCloseHistory').addEventListener('click',() => toggleHistory(false));$('aiHistoryBackdrop').addEventListener('click',() => toggleHistory(false));$('aiJump').addEventListener('click',() => toBottom());
    scroll.addEventListener('scroll',() => {$('aiJump').hidden = app.dataset.mode !== 'chat' || nearBottom();},{passive:true});
    document.addEventListener('click',e => {if(!$('aiExamples').contains(e.target))$('aiExamples').open = false;});
    document.addEventListener('keydown',e => {
        if(e.key === 'Escape'){if($('aiExamples').open){$('aiExamples').open = false;$('aiExamples').querySelector('summary').focus();}else if(app.dataset.historyOpen === 'true')toggleHistory(false);}
        if(e.key === 'Tab' && mobile.matches && app.dataset.historyOpen === 'true'){const items = [...history.querySelectorAll('a,button')].filter(n => n.getClientRects().length),first = items[0],last = items.at(-1);if(e.shiftKey && document.activeElement === first){e.preventDefault();last.focus();}else if(!e.shiftKey && document.activeElement === last){e.preventDefault();first.focus();}}
    });
    mobile.addEventListener('change',() => {toggleHistory(false,false);resizeInput();});window.addEventListener('resize',resizeInput);
    document.addEventListener('aurel:planner-start',() => {newThread(false);toggleHistory(false,false);});
    document.addEventListener('aurel:place-restrictions',() => {if(current())renderConversation();});
    document.addEventListener('aurel:visit-date',()=>{for(const input of document.querySelectorAll('.ai-visit-date')){input.value=window.AurelPlanner.visitDate();input.dispatchEvent(new Event('change'));}});
    document.addEventListener('angel:hours-refresh',()=>{for(const hours of document.querySelectorAll('.ai-place-hours')){if(hours.refreshHours)hours.refreshHours();else hours.refreshPresentation?.();}});
    window.addEventListener('pagehide',() => {disposeMaps();for(const job of jobs.values()){job.controller?.abort('cancelled');clearTimeout(job.slowTimer);clearTimeout(job.totalTimer);}});
    newThread(false);toggleHistory(false,false);loadHistory();
})();
