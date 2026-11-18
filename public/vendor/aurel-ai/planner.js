/* Two-step itinerary editor. AI remains the existing authenticated assistant. */
(() => {
    'use strict';
    const root = document.getElementById('tripPlanner'); if (!root) return;
    const $ = id => document.getElementById(id);
    const activityCopy = JSON.parse($('tripActivityCopy')?.textContent || '{}');
    const description = text => activityCopy[text] ?? text;
    const node = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };
    const clone = data => JSON.parse(JSON.stringify(data));
    const uid = () => globalThis.crypto?.randomUUID?.() || 'planner-' + Date.now() + '-' + Math.random().toString(36).slice(2);
    const money = value => new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
    const dateForDay=(start,index)=>{if(!start)return null;const date=new Date(start+'T00:00:00Z');date.setUTCDate(date.getUTCDate()+index);return date.toISOString().slice(0,10);};
    const stayLabels = {aurel_hotel:'Cần chỗ ở', own_accommodation:'Đã có chỗ ở', day_trip:'Đi trong ngày'};
    let trip = null, undo = [], version = 0, dirty = false, busy = false, editing = null, destinations = [], libraryPage = 1;
    let restrictions = [];
    let hoursTimer = null, hoursSequence = 0, lastVisitDate = null;
    const normalizePlace = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/gi,'d').toLowerCase().replace(/[^a-z0-9]/g,'');
    const normalizeLocation = value => normalizePlace(value).replace(/^(thanhpho|tinh)|city$/g,'');
    function excluded(item, location) {
        if (item.excluded_from_ai || ['closedpermanently','permanentlyclosed'].includes(normalizePlace(item.operating_status || item.business_status))) return true;
        location = typeof location === 'number' ? destinations.find(d => Number(d.id) === location)?.name : location;
        const city = normalizeLocation(location || trip?.destination), name = normalizePlace(item.name || item.title);
        return restrictions.some(rule => {
            if (normalizeLocation(rule.location_name) !== city) return false;
            if (rule.provider_place_id && rule.provider_place_id === item.provider_place_id) return true;
            if (!rule.names.some(value => normalizePlace(value) === name)) return false;
            if (rule.coordinates && item.coordinates) {
                const lat=(rule.coordinates[1]+item.coordinates[1])*Math.PI/360;
                return Math.hypot((rule.coordinates[0]-item.coordinates[0])*Math.cos(lat),rule.coordinates[1]-item.coordinates[1])*111320 <= 150;
            }
            return !rule.address || !item.address || normalizePlace(rule.address) === normalizePlace(item.address);
        });
    }
    let generationTimer = null;
    function generationStatus(active) {
        clearInterval(generationTimer); generationTimer = null; $('tripGenerationStatus').hidden = !active;
        if (!active) return;
        const started = Date.now(), elapsed = $('tripGenerationElapsed');
        const update = () => { const seconds = Math.floor((Date.now()-started)/1000); elapsed.textContent = 'Đã chờ ' + String(Math.floor(seconds/60)).padStart(2,'0') + ':' + String(seconds%60).padStart(2,'0'); };
        update(); generationTimer = setInterval(update,1000);
    }
    function mapUrl(item) {
        if (item.provider === 'google_sheet' || item.maps_url !== undefined) {
            try {
                const url = new URL(item.maps_url);
                if (url.protocol !== 'https:' || url.username || url.password || url.port || /[\s\\]/.test(item.maps_url)) return null;
                const allowed = ['google.com','www.google.com','maps.google.com'].includes(url.hostname) && (url.hostname === 'maps.google.com' || /^\/maps(?:\/|$)/.test(url.pathname))
                    || url.hostname === 'maps.app.goo.gl' && url.pathname.length > 1 || url.hostname === 'goo.gl' && url.pathname.startsWith('/maps/');
                return allowed ? item.maps_url : null;
            } catch { return null; }
        }
        const address = item.address || item.place_name;
        const query = [...new Set([item.name || item.title,address,address ? null : trip?.destination].filter(Boolean))].join(', ');
        return query ? 'https://www.google.com/maps/search/?' + new URLSearchParams({api:'1',query}) : null;
    }
    const identities = new Map();
    function keyFor(action, data) {
        const signature = action + JSON.stringify(data);
        if (!identities.has(signature)) identities.set(signature, uid());
        return identities.get(signature);
    }
    function button(text, action, cls = 'trip-button') {
        const b = node('button', cls, text); b.type = 'button'; b.addEventListener('click', action); return b;
    }
    function notice(text = '', error = false, login = false) {
        const box = $('tripNotice'); box.replaceChildren(document.createTextNode(text)); box.hidden = !text; box.dataset.error = String(error);
        if (login) { const a = node('a', '', ' Đăng nhập'); a.href = '/login'; box.append(a); }
    }
    async function api(path, method = 'GET', data, blob = false) {
        const token = localStorage.getItem('token');
        if (!token) { const e = Error('Đăng nhập để lấy điểm đến và lưu lịch trình của bạn.'); e.login = true; throw e; }
        const controller = new AbortController(), timer = setTimeout(() => controller.abort(), 300000);
        try {
            const response = await fetch('/api' + path, {method, signal:controller.signal, headers:{Accept:blob ? 'application/pdf' : 'application/json', Authorization:'Bearer ' + token,
                ...(data ? {'Content-Type':'application/json','Idempotency-Key':keyFor(method + path, data)} : {})}, ...(data ? {body:JSON.stringify(data)} : {})});
            if (blob && response.ok) {
                if (!response.headers.get('Content-Type')?.includes('application/pdf')) throw Error('Máy chủ chưa trả về tệp PDF.');
                return response.blob();
            }
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                const detail = Object.values(body.errors || {}).flat()[0];
                const e = Error(response.status === 401 ? 'Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.' : response.status === 409 ? 'Lịch đã thay đổi ở phiên khác hoặc yêu cầu còn đang xử lý. Mở lại lịch đã lưu trước khi tiếp tục.' : detail || body.message || 'Chưa xử lý được yêu cầu. Bạn thử lại nhé.');
                e.login = response.status === 401; throw e;
            }
            return body;
        } catch (e) {
            if (controller.signal.aborted) throw Error('Yêu cầu mất nhiều thời gian. Bạn có thể thử lại; hệ thống sẽ tránh tạo lịch trùng.');
            throw e;
        } finally { clearTimeout(timer); }
    }
    function formData() {
        return {location_id:Number($('tripDestination').value), duration_days:Number($('tripDays').value), people:Number($('tripPeople').value),
            estimated_budget:$('tripBudget').value === '' ? null : Number($('tripBudget').value), stay_mode:document.querySelector('[name=tripStay]:checked').value};
    }
    function itemData(item) {
        return {id:item.id || null, item_type:item.item_type || 'activity', title:item.title, place_name:item.place_name || null,
            description:item.description || null, start_time:item.start_time?.slice(0,5) || null,
            end_time:item.end_time?.slice(0,5) || null, end_day_offset:Number(item.end_day_offset || 0),
            ...(item.source_place_id ? {source_place_id:Number(item.source_place_id)} : {}), estimated_cost:item.estimated_cost == null ? null : Number(item.estimated_cost),
            ...(item.photo_reference ? {photo_reference:item.photo_reference} : {})};
    }
    function context() {
        if (!trip || trip.readOnly) return null;
        return {location_id:trip.location_id, duration_days:trip.duration_days, people:trip.people, visit_start_date:trip.visit_start_date || null,
            ...(trip.pace?{pace:trip.pace}:{}),...(trip.interests?{interests:trip.interests}:{}),...(trip.planning_reference?{planning_reference:trip.planning_reference}:{}),
            estimated_budget:trip.estimated_budget, stay_mode:trip.stay_mode, days:trip.days.map(d => ({items:d.items.map(itemData)}))};
    }
    function fromResource(data) {
        return {id:data.id, revision:data.revision, location_id:data.location.id, destination:data.location.name, title:data.title,
            duration_days:data.total_days, people:data.party.total, estimated_budget:data.budget.limit, stay_mode:data.stay_mode,
            pace:data.pace,interests:data.interests || [],generation_warnings:data.planning_warnings || [],
            provisional:data.provisional, budget:data.budget, visit_start_date:data.provisional ? null : data.start_date, planning_area:data.planning_area || null, dates:data.days.map(d => d.date), days:data.days.map(d => ({items:d.items.map(i => ({...i, ...itemData(i)}))})),
            readOnly:!['draft','saved'].includes(data.status) || Boolean(data.party.children?.length || data.party.infants?.length || data.lodging?.hotel_id), status:data.status};
    }
    function setBusy(value, label = 'Đang xử lý…') {
        busy = value;
        root.querySelectorAll('#tripForm input,#tripForm select,#tripForm button,#tripSave,#tripPdf,#tripUndo,#tripChangeInfo,#tripSavedButton,#tripNewButton,#tripDaysList button').forEach(b => b.disabled = value);
        $('tripForm').setAttribute('aria-busy', String(value));
        $('tripGenerate').querySelector('span').textContent = value ? label : trip ? 'Cập nhật thông tin' : 'Tạo lịch trình với AI';
        if (!value) {
            if (trip) render();
            $('tripGenerate').disabled = !destinations.length;
            $('tripUndo').disabled = !undo.length || trip?.readOnly;
            if (trip?.readOnly) root.querySelectorAll('#tripSave,#tripChangeInfo,#tripDaysList button').forEach(b => b.disabled = true);
            syncStay();
        }
    }
    function syncStay() {
        const dayTrip = document.querySelector('[name=tripStay]:checked').value === 'day_trip';
        if (dayTrip) $('tripDays').value = 1;
        $('tripDays').disabled = dayTrip || busy; $('tripDayTripHint').hidden = !dayTrip;
    }
    function step(number) {
        root.dataset.step = number; $('tripSetup').hidden = number !== 1; $('tripEditor').hidden = number !== 2;
        $('tripStepOne').toggleAttribute('aria-current', number === 1); $('tripStepTwo').toggleAttribute('aria-current', number === 2);
        (number === 1 ? $('tripStepOne') : $('tripStepTwo')).setAttribute('aria-current','step');
        $('tripReturnToBoard').hidden = !trip; window.dispatchEvent(new Event('resize'));
    }
    function remember() {
        const snapshot = clone(trip); delete snapshot.id; delete snapshot.revision;
        undo.push(snapshot); if (undo.length > 60) undo.shift();
    }
    function changed() { version++; dirty = true; render(); }
    function undoEdit() {
        if (!undo.length || busy || trip.readOnly) return;
        trip = {...undo.pop(), id:trip.id, revision:trip.revision}; changed(); fillForm(); notice('Đã hoàn tác thay đổi gần nhất.');
    }
    function confirmChange(text) {
        const dialog = $('tripConfirmDialog'); $('tripConfirmText').textContent = text; dialog.showModal();
        return new Promise(resolve => {
            let accepted = false;
            $('tripConfirmAccept').onclick = () => { accepted = true; dialog.close(); };
            $('tripConfirmCancel').onclick = () => dialog.close();
            dialog.addEventListener('close', () => resolve(accepted), {once:true});
        });
    }
    function fillForm() {
        if (!trip) return;
        $('tripDestination').value = trip.location_id; $('tripDays').value = trip.duration_days; $('tripPeople').value = trip.people;
        $('tripBudget').value = trip.estimated_budget ?? ''; const option = document.querySelector('[name=tripStay][value="' + trip.stay_mode + '"]'); if (option) option.checked = true;
        $('tripGenerate').querySelector('span').textContent = 'Cập nhật thông tin'; syncStay();
    }
    async function loadDestinations() {
        $('tripRetryDestinations').hidden = true; $('tripDestinationStatus').textContent = 'Đang tải…';
        try {
            const result = await api('/v2/itineraries/destinations'); destinations = result.data || []; restrictions = result.place_restrictions || [];
            document.dispatchEvent(new Event('aurel:place-restrictions'));
            $('tripDestination').replaceChildren(new Option('Chọn điểm đến của bạn', ''), ...destinations.map(d => new Option(d.name, d.id)));
            $('tripDestination').disabled = !destinations.length; $('tripGenerate').disabled = !destinations.length;
            $('tripDestinationStatus').textContent = destinations.length ? '' : 'Chưa có điểm đến được hỗ trợ.';
            if (trip) fillForm();
        } catch (e) { $('tripDestinationStatus').textContent = e.message; $('tripRetryDestinations').hidden = false; notice(e.message, true, e.login); }
    }
    function dayWarnings(items) {
        const warnings = [];
        for (let i = 1; i < items.length; i++) {
            const previous = items[i-1], item = items[i];
            if (previous.end_time && item.start_time && item.start_time < previous.end_time) warnings.push('Giờ của “' + item.title + '” đang trùng hoặc đứng trước hoạt động phía trên.');
        }
        return warnings;
    }
    function renderHours(container, check) {
        container.replaceChildren();container.warningTarget?.replaceChildren();if(!check)return;window.AngelOpeningHours.render(container,check.display,check,{warningTarget:container.warningTarget});
    }
    function queueHours() {
        clearTimeout(hoursTimer); const sequence=++hoursSequence;
        if(!trip || trip.readOnly)return;
        hoursTimer=setTimeout(async()=>{
            const data=context();if(!data)return;const signature=JSON.stringify(data);
            try {
                const result=await api('/v2/itineraries/planner/check-hours','POST',data);
                if(sequence!==hoursSequence || signature!==JSON.stringify(context()))return;
                result.days.forEach((day,di)=>day.items.forEach((item,index)=>{
                    const target=$('tripDaysList').querySelector('[data-hours-item="'+di+':'+index+'"]');if(target){trip.days[di].items[index].opening_check=item.opening_check;renderHours(target,item.opening_check);}
                }));
            } catch(e) {
                if(sequence===hoursSequence && signature===JSON.stringify(context()))$('tripDaysList').querySelectorAll('[data-hours-item]').forEach(target=>{
                    const [day,index]=target.dataset.hoursItem.split(':').map(Number),item=trip.days[day].items[index],old=item.opening_check;
                    const check={status:'unknown',display:old?.display?.has_data?{...old.display,status:'unknown',summary:'Giờ hoạt động: Chưa cập nhật được trạng thái hiện tại'}:null};
                    item.opening_check=check;renderHours(target,check);target.warningTarget?.append(node('p','trip-schedule-warning','Chưa đối chiếu lại được giờ ghé.'));
                });
            }
        },120);
    }
    const photoRequests=new Map(), photoQueue=[]; let activePhotos=0;
    function pumpPhotos() {
        while(activePhotos<2 && photoQueue.length) {
            const task=photoQueue.shift(); activePhotos++;
            api('/v2/itineraries/planner/place-photo','POST',task.data).then(task.resolve,task.reject).finally(()=>{activePhotos--;pumpPhotos();});
        }
    }
    function requestPhoto(data) {
        const key=JSON.stringify(data);
        if(!photoRequests.has(key)) {
            const request=new Promise((resolve,reject)=>{photoQueue.push({data,resolve,reject});});
            photoRequests.set(key,request);pumpPhotos();request.catch(()=>photoRequests.delete(key));
        }
        return photoRequests.get(key);
    }
    const photoObserver='IntersectionObserver' in window?new IntersectionObserver(entries=>{
        for(const entry of entries)if(entry.isIntersecting){photoObserver.unobserve(entry.target);entry.target.loadPhoto?.();}
    },{rootMargin:'250px'}):null;
    function renderPhoto(body,item) {
        const hasPlace=Boolean(item.place_name || item.address || ['attraction','restaurant','hotel'].includes(item.item_type));
        if(!hasPlace && !item.image)return;
        const data=item.photo_reference?{photo_reference:item.photo_reference}:trip.id && item.id && item.photo_lookup?{itinerary_id:trip.id,item_id:item.id}:null;
        const status=node('div','trip-photo-status');status.setAttribute('role','status');
        const identity=JSON.stringify([item.title,item.place_name]);
        const unavailable=(text='Chưa có ảnh phù hợp cho địa điểm này.',retry=null)=>{
            status.replaceChildren(node('span','',text));body.prepend(status);
            if(retry)status.append(button('Thử lại',retry,'trip-text-button'));
        };
        const show=image=>{
            const figure=window.AurelPlacePhotos?.render(body,image,item.title,{onError:()=>unavailable('Chưa tải được ảnh địa điểm.',()=>show(image))});
            if(figure){status.remove();body.prepend(figure);}else unavailable();
        };
        const load=async()=>{
            if(!body.isConnected)return;
            status.replaceChildren(node('span','','Đang tải ảnh địa điểm…'));body.prepend(status);
            try {
                const result=await requestPhoto({...data,title:item.title,place_name:item.place_name || null});
                if(identity!==JSON.stringify([item.title,item.place_name]))return;
                if(result.image && trip?.days.some(day=>day.items.includes(item)))item.image=result.image;
                if(body.isConnected){if(result.image)show(result.image);else unavailable();}
            }catch(e){if(body.isConnected)unavailable('Chưa tải được ảnh địa điểm.',load);}
        };
        if(item.image){show(item.image);return;}
        if(!data){unavailable();return;}
        status.textContent='Đang tải ảnh địa điểm…';body.prepend(status);status.loadPhoto=load;
        if(photoObserver)photoObserver.observe(status);else requestAnimationFrame(load);
    }
    function render() {
        if (!trip) return;
        $('tripTitle').textContent = trip.destination || trip.title || 'Lịch trình của bạn';
        $('tripSummary').textContent = trip.duration_days + ' ngày · ' + (trip.people ? trip.people + ' người · ' : '') + (trip.legacy ? 'Lịch cũ' : stayLabels[trip.stay_mode] || '') + ' · ' + (trip.estimated_budget == null ? 'Chưa chốt ngân sách' : money(trip.estimated_budget));
        let notes=$('tripPlanningNotes');if(!notes){notes=node('div','trip-planning-notes');notes.id='tripPlanningNotes';$('tripSummary').after(notes);}
        notes.replaceChildren();
        const messages=dirty?[]:[...(trip.budget?.warnings || []),...(trip.generation_warnings || [])];
        if(dirty && trip.estimated_budget!=null){const total=trip.days.flatMap(d=>d.items).reduce((sum,i)=>sum+Number(i.estimated_cost ?? i.ticket_estimate?.amount ?? 0),0);
            if(total>trip.estimated_budget)messages.push('Phần hoạt động đang chỉnh vượt ngân sách '+money(total-trip.estimated_budget)+'. Chưa cộng các khoản còn thiếu.');}
        if(trip.estimated_budget!=null)messages.push(trip.budget?.coverage_notice || 'Các khoản ăn uống, lưu trú và di chuyển chưa có giá thì chưa được cộng vào tổng tạm tính.');
        for(const message of new Set(messages))notes.append(node('p','trip-activity-place',message));notes.hidden=!messages.length;
        $('tripSaveStatus').textContent = trip.readOnly ? 'Chế độ xem' : dirty ? 'Có thay đổi chưa lưu' : trip.id ? 'Đã lưu' : 'Chưa lưu';
        if(lastVisitDate!==trip.visit_start_date){lastVisitDate=trip.visit_start_date;document.dispatchEvent(new Event('aurel:visit-date'));}
        $('tripPlanningArea').hidden = !trip.planning_area?.notice; $('tripPlanningArea').textContent = trip.planning_area?.notice || '';
        $('tripUndo').disabled = !undo.length || busy || trip.readOnly;
        $('tripSave').disabled = busy || trip.readOnly; $('tripPdf').disabled = busy || !trip.id && trip.readOnly; $('tripChangeInfo').disabled = trip.readOnly;
        $('tripPdf').hidden = Boolean(trip.legacy);
        photoObserver?.disconnect();
        const fragment = document.createDocumentFragment();
        trip.days.forEach((day, di) => {
            const section = node('section', 'trip-day'); section.dataset.day = di;
            const actualDate=dateForDay(trip.visit_start_date,di);
            const heading = node('div','trip-day-heading'), title = node('h2','','Ngày ' + (di+1)+(actualDate?' · '+actualDate.split('-').reverse().join('/'):''));
            heading.append(title, node('span','',day.items.length + ' hoạt động')); section.append(heading);
            if (!day.items.length) section.append(node('p','trip-empty','Một ngày còn để ngỏ. Thêm hoạt động theo ý bạn.'));
            day.items.forEach((item, index) => {
                const card = node('article','trip-activity'); card.dataset.item = index;
                const time = node('div','trip-activity-time',item.start_time || 'Chưa đặt giờ');
                if (item.end_time) time.append(node('span','','đến ' + item.end_time+(item.end_day_offset?' (ngày hôm sau)':'')));
                const body = node('div','trip-activity-content');
                renderPhoto(body,item);
                body.append(node('h3','',item.title));
                if (item.place_name || item.address) body.append(node('p','trip-activity-place',item.place_name || item.address));
                if (item.description) body.append(node('p','trip-activity-description',description(item.description)));
                const opening=node('div','trip-opening-hours'),hoursWarning=node('div','trip-hours-warning');opening.warningTarget=hoursWarning;opening.dataset.hoursItem=di+':'+index;renderHours(opening,item.opening_check);
                if (excluded(item)) body.append(node('p','trip-schedule-warning','Địa điểm đã được báo đóng cửa. Bạn nên chọn điểm khác.'));
                const amount = item.estimated_cost ?? item.ticket_estimate?.amount;
                if (amount != null) body.append(node('p','trip-activity-cost',(item.estimated_cost == null ? 'Vé dự kiến: ' : 'Chi phí dự kiến: ') + money(amount)));
                body.append(opening);
                if ((item.place_name || item.address || item.kind === 'place') && mapUrl(item)) {
                    const a = node('a','trip-text-button trip-map-link','Xem trên Google Maps'); a.href = mapUrl(item); a.target = '_blank'; a.rel = 'noopener noreferrer'; body.append(a);
                }
                body.append(hoursWarning);
                if (!trip.readOnly) {
                    const actions = node('div','trip-activity-actions');
                    actions.append(button('Chỉnh sửa', () => editActivity(di,index), ''), button('↑', () => move(di,index,-1), ''), button('↓', () => move(di,index,1), ''), button('Xóa', () => { if (busy) return; remember(); trip.days[di].items.splice(index,1); changed(); }, 'trip-remove'));
                    actions.children[1].disabled = index === 0; actions.children[1].setAttribute('aria-label','Đưa ' + item.title + ' lên');
                    actions.children[2].disabled = index === day.items.length-1; actions.children[2].setAttribute('aria-label','Đưa ' + item.title + ' xuống');
                    body.append(actions);
                }
                card.append(time,body); section.append(card);
            });
            for (const warning of dayWarnings(day.items)) section.append(node('p','trip-schedule-warning',warning));
            if (!trip.readOnly) section.append(button('+ Thêm hoạt động', () => editActivity(di), 'trip-day-add'));
            fragment.append(section);
        });
        $('tripDaysList').replaceChildren(fragment); queueHours();
    }
    function move(di,index,delta) {
        if (busy) return; const items = trip.days[di].items, target = index + delta; if (target < 0 || target >= items.length) return;
        remember(); [items[index], items[target]] = [items[target], items[index]]; changed();
        $('tripDaysList').querySelector('[data-day="' + di + '"] [data-item="' + target + '"] button')?.focus();
    }
    const overnightField=node('div','trip-field trip-field-full'),overnightLabel=node('label','trip-overnight-label'),overnightInput=node('input');overnightInput.type='checkbox';overnightInput.id='tripActivityOvernight';
    overnightLabel.append(overnightInput,document.createTextNode(' Kết thúc vào ngày hôm sau'));overnightField.append(overnightLabel);$('tripActivityEnd').parentElement.after(overnightField);
    function editActivity(day,index = null,seed = null) {
        if (busy || trip.readOnly) return; editing = {day,index,seed}; const item = seed || (index === null ? {} : trip.days[day].items[index]);
        $('tripActivityTitle').textContent = index === null ? 'Thêm hoạt động' : 'Chỉnh sửa hoạt động';
        $('tripActivityDay').replaceChildren(...trip.days.map((_,i) => {const date=dateForDay(trip.visit_start_date || seed?.pending_visit_start_date,i);return new Option('Ngày '+(i+1)+(date?' · '+date.split('-').reverse().join('/'):''),i);}));
        $('tripActivityDay').value = day;
        for (const [id, value] of Object.entries({tripActivityName:item.title, tripActivityPlace:item.place_name || item.address, tripActivityCost:item.estimated_cost,
            tripActivityStart:item.start_time, tripActivityEnd:item.end_time, tripActivityNotes:description(item.description)})) $(id).value = value ?? '';
        $('tripActivityEnd').setCustomValidity(''); $('tripActivityDialog').showModal(); $('tripActivityName').focus();
        $('tripActivityOvernight').checked=Boolean(item.end_day_offset);
    }
    $('tripActivityForm').addEventListener('submit', e => {
        e.preventDefault(); if (!editing || busy) return;
        const start = $('tripActivityStart').value, end = $('tripActivityEnd').value;
        $('tripActivityEnd').setCustomValidity(start && end && end <= start && !$('tripActivityOvernight').checked ? 'Giờ kết thúc phải sau giờ bắt đầu.' : '');
        if (!$('tripActivityForm').reportValidity()) return;
        const {day,index} = editing, targetDay = Number($('tripActivityDay').value);
        if (trip.days[targetDay].items.length >= 50 && (day !== targetDay || index === null)) { notice('Mỗi ngày tối đa 50 hoạt động.',true); return; }
        const original = index === null ? (editing.seed || {}) : trip.days[day].items[index]; remember();
        if(!trip.visit_start_date && editing.seed?.pending_visit_start_date){trip.visit_start_date=editing.seed.pending_visit_start_date;trip.provisional=false;}
        const item = {...original, item_type:original.item_type || 'activity', title:$('tripActivityName').value.trim(), place_name:$('tripActivityPlace').value.trim(),
            description:$('tripActivityNotes').value.trim(), start_time:start || null, end_time:end || null,
            end_day_offset:$('tripActivityOvernight').checked?1:0,
            estimated_cost:$('tripActivityCost').value === '' ? null : Number($('tripActivityCost').value), manually_edited:true, travel_from_previous:null,opening_check:null};
        if (original.title !== item.title || (original.place_name || original.address || '') !== (item.place_name || '')) { delete item.image; delete item.photo_reference; delete item.photo_lookup; delete item.excluded_from_ai; delete item.coordinates; delete item.operating_status; }
        if((original.place_name || original.address || '') !== (item.place_name || '')){delete item.source_place_id;delete item.maps_url;delete item.provider;}
        if (index !== null) trip.days[day].items.splice(index,1);
        trip.days[targetDay].items.splice(index !== null && day === targetDay ? index : trip.days[targetDay].items.length,0,item);
        $('tripActivityDialog').close(); editing = null; changed();
    });
    $('tripActivityEnd').addEventListener('input', () => $('tripActivityEnd').setCustomValidity(''));
    $('tripActivityStart').addEventListener('input', () => $('tripActivityEnd').setCustomValidity(''));
    $('tripActivityOvernight').addEventListener('change',()=> $('tripActivityEnd').setCustomValidity(''));
    async function start(data, manual = false) {
        if (busy) return;
        if (trip && trip.location_id === data.location_id) {
            if (data.duration_days < trip.days.length && trip.days.slice(data.duration_days).some(d => d.items.length) && !await confirmChange('Giảm số ngày sẽ bỏ hoạt động ở các ngày cuối. Bạn có thể hoàn tác sau đó.')) return;
            remember(); trip = {...trip, ...data, days:Array.from({length:data.duration_days},(_,i) => trip.days[i] || {items:[]})}; changed(); step(2); notice('Đã cập nhật thông tin. Các hoạt động còn lại được giữ nguyên.'); return;
        }
        if (trip && dirty && !await confirmChange('Đổi điểm đến sẽ bắt đầu một lịch mới. Lịch đang chỉnh có thay đổi chưa lưu. Tiếp tục?')) return;
        if (manual) {
            trip = {...data, destination:destinations.find(d => Number(d.id) === data.location_id)?.name, days:Array.from({length:data.duration_days},() => ({items:[]})), provisional:true};
            dirty = true;
        } else {
            setBusy(true, 'AI đang lên lịch…'); generationStatus(true); notice('AI đang tìm địa điểm và sắp xếp lịch trình. Quá trình này có thể mất vài phút.');
            try {
                const result = await api('/v2/itineraries/planner/generate','POST',data); trip = fromResource(result.data);trip.generation_warnings=result.planning_warnings || []; dirty = false;
            } catch (e) {
                $('tripManualFallback').hidden = false;
                notice(e.message,true,e.login); return;
            } finally { generationStatus(false); setBusy(false); }
        }
        undo = []; version++; $('tripManualFallback').hidden = true; render(); step(2); fillForm();
        document.dispatchEvent(new Event('aurel:planner-start'));
        notice(manual ? 'Bạn đang lập lịch thủ công. Có thể thêm hoạt động hoặc trao đổi với trợ lý bên phải.' : 'Lịch AI đã sẵn sàng. Bạn có thể chỉnh lại từng hoạt động. Giờ tham quan và phương tiện là dự kiến.');
    }
    $('tripForm').addEventListener('submit', e => { e.preventDefault(); if ($('tripForm').reportValidity()) start(formData()); });
    $('tripContinueManual').addEventListener('click', () => { if ($('tripForm').reportValidity()) start(formData(),true); });
    $('tripNewButton').addEventListener('click', async () => {
        if (busy || dirty && !await confirmChange('Lịch hiện tại có thay đổi chưa lưu. Bắt đầu lịch mới sẽ bỏ phần chưa lưu; các lịch đã lưu vẫn được giữ. Tiếp tục?')) return;
        trip = null; undo = []; editing = null; dirty = false; version++; identities.clear();
        clearTimeout(hoursTimer);hoursSequence++;
        $('tripForm').reset(); $('tripDaysList').replaceChildren(); $('tripManualFallback').hidden = true;
        $('tripGenerate').querySelector('span').textContent = 'Tạo lịch trình với AI'; syncStay(); step(1); notice();
        document.dispatchEvent(new Event('aurel:planner-start')); $('tripDestination').focus();
    });
    async function save() {
        const data = context(); if (!data) throw Error('Lịch trình này hiện chỉ có thể xem.');
        if (trip.id) data.expected_revision = trip.revision;
        const result = await api('/v2/itineraries/' + (trip.id ? trip.id + '/planner' : 'planner'),trip.id ? 'PUT' : 'POST',data);
        trip = fromResource(result.data);
        // Deleted rows no longer have reusable server IDs when a later undo restores them.
        const liveIds = new Set(trip.days.flatMap(day => day.items.map(item => item.id)));
        for (const snapshot of undo) for (const day of snapshot.days) for (const item of day.items) if (!liveIds.has(item.id)) delete item.id;
        dirty = false; render(); return trip.id;
    }
    $('tripSave').addEventListener('click', async () => {
        if (busy) return; setBusy(true);
        try { await save(); notice('Đã lưu lịch trình của bạn.'); } catch (e) { notice(e.message,true,e.login); } finally { setBusy(false); }
    });
    $('tripPdf').addEventListener('click', async () => {
        if (busy) return; setBusy(true);
        try {
            const id = dirty || !trip.id ? await save() : trip.id;
            const file = await api('/v2/itineraries/' + id + '/pdf','GET',undefined,true), url = URL.createObjectURL(file), a = node('a');
            a.href = url; a.download = 'lich-trinh-' + id + '.pdf'; document.body.append(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(url),1000);
            notice('Đã tải PDF của lịch trình đã lưu.');
        } catch (e) { notice(e.message,true,e.login); } finally { setBusy(false); }
    });
    async function loadLibrary(append = false) {
        const refresh=$('tripLibraryRefresh');refresh.disabled=true;refresh.setAttribute('aria-busy','true');
        const list = $('tripLibraryList'); $('tripLibraryMore').hidden = true;
        if (!append) { libraryPage = 1; list.replaceChildren(node('p','','Đang tải lịch trình…')); }
        try {
            const results = await Promise.allSettled([api('/v2/itineraries?per_page=20&page=' + libraryPage), ...(!append ? [api('/itineraries')] : [])]);
            const modern = results[0], legacy = results[1];
            if (modern.status === 'rejected' && (!legacy || legacy.status === 'rejected')) throw modern.reason;
            const result = modern.status === 'fulfilled' ? modern.value : {data:[]};
            if (!append) list.replaceChildren();
            for (const data of result.data || []) {
                const row = node('div','trip-library-row'), info = node('div');
                info.append(node('strong','',data.title),node('p','',data.total_days + ' ngày · ' + data.party.total + ' người · ' + (data.status === 'saved' ? 'Đã lưu' : 'Bản nháp')));
                row.append(info,button('Mở', async () => {
                    if (dirty && !await confirmChange('Lịch đang chỉnh có thay đổi chưa lưu. Bạn muốn mở lịch đã lưu khác?')) return;
                    try { const latest = await api('/v2/itineraries/' + data.id); trip = fromResource(latest.data); dirty = false; undo = []; version++; render(); fillForm(); step(2); $('tripLibraryDialog').close(); document.dispatchEvent(new Event('aurel:planner-start')); notice(trip.readOnly ? 'Lịch có thông tin nhóm khách chi tiết; mở ở chế độ xem để giữ nguyên dữ liệu.' : 'Đã mở lịch trình đã lưu.'); }
                    catch (e) { notice(e.message,true,e.login); }
                })); list.append(row);
            }
            if (legacy?.status === 'fulfilled' && Array.isArray(legacy.value)) for (const data of legacy.value.filter(d => Number(d.schema_version) !== 2)) {
                const row = node('div','trip-library-row'), info = node('div'); info.append(node('strong','',data.title),node('p','','Lịch cũ · Chế độ xem'));
                row.append(info,button('Xem',async () => {
                    if (dirty && !await confirmChange('Lịch đang chỉnh có thay đổi chưa lưu. Bạn muốn mở lịch cũ?')) return;
                    try {
                        const old = await api('/itineraries/' + data.id), count = Math.max(1,Number(old.total_days) || old.days?.length || 1);
                        trip = {id:old.id, legacy:true, readOnly:true, destination:old.destination, title:old.title, duration_days:count, people:old.num_people || 0,
                            estimated_budget:old.estimated_budget == null ? null : Number(old.estimated_budget), stay_mode:'own_accommodation',
                            days:Array.from({length:count},(_,i) => ({items:old.days?.find(d => Number(d.day_number) === i+1)?.items || []}))};
                        dirty = false; undo = []; version++; render(); step(2); $('tripLibraryDialog').close(); notice('Đang xem lịch cũ. Nội dung được giữ nguyên.');
                    } catch (e) { notice(e.message,true,e.login); }
                })); list.append(row);
            }
            if (!list.children.length) list.append(node('p','','Bạn chưa có lịch trình. Bắt đầu bằng thông tin chuyến đi của bạn.'));
            $('tripLibraryMore').hidden = !(result.meta?.current_page < result.meta?.last_page);
        } catch (e) { list.replaceChildren(node('p','',e.message)); }
        finally { refresh.disabled=false;refresh.removeAttribute('aria-busy'); }
    }
    function proposal(timeline, message, state) {
        const box = node('div','trip-proposal'); box.dataset.resolved = String(Boolean(message.plannerDecision));
        const target = timeline.target_day, window = timeline.target_window;
        const label = target ? (window ? (window.start >= '12:00' ? 'buổi chiều ' : 'buổi sáng ') : '') + 'ngày ' + target : 'toàn bộ lịch trình';
        const duration = Array.isArray(timeline.days) ? timeline.days.length : 0;
        const proposalDescription = !target && trip && duration !== trip.duration_days
            ? 'Đề xuất đổi từ ' + trip.duration_days + ' ngày sang ' + duration + ' ngày và sắp xếp lại toàn bộ hoạt động.'
            : 'Đề xuất thay thế ' + label + '.';
        box.append(node('p','', message.plannerDecision || (message.plannerVersion === undefined ? 'Đề xuất từ lịch sử trò chuyện. Gửi yêu cầu mới để AI xem lịch đang mở trước khi áp dụng.' : proposalDescription + ' Các hoạt động hiện tại chỉ đổi khi bạn chọn Áp dụng.')));
        if (message.plannerDecision) return box;
        const controls = node('div');
        const apply = button('Áp dụng', () => {
            if (busy || !trip || trip.readOnly) return;
            if (message.plannerVersion !== version) { notice('Lịch đã được chỉnh sau khi gửi tin nhắn này. Hãy yêu cầu AI đề xuất lại trên lịch hiện tại.',true); return; }
            if (Number(state?.location_id) !== trip.location_id) { notice('Đề xuất khác điểm đến. Hãy cập nhật thông tin chuyến đi trước.',true); return; }
            // A full proposal may resize the trip. A single-day revision must keep the other days intact.
            if (duration < 1 || duration > 30 || (state?.duration_days != null && Number(state.duration_days) !== duration)
                || (target != null && (!Number.isInteger(target) || target < 1 || target > trip.days.length || duration !== trip.days.length))
                || timeline.days.some(d => !Number.isInteger(d.day_number) || d.day_number < 1 || d.day_number > duration)
                || new Set(timeline.days.map(d => d.day_number)).size !== duration) {
                notice('Đề xuất chưa có đủ các ngày hợp lệ để áp dụng. Hãy yêu cầu AI đề xuất lại.',true); return;
            }
            if (duration > 1 && trip.stay_mode === 'day_trip') { notice('Lịch nhiều ngày cần chỗ ở. Hãy đổi nhu cầu lưu trú trong thông tin chuyến đi rồi yêu cầu AI đề xuất lại.',true); return; }
            const selected = target ? timeline.days.filter(d => d.day_number === target) : timeline.days;
            if (!selected.length || selected.some(d => !Array.isArray(d.items) || d.items.length > 50)) { notice('Đề xuất chưa phù hợp để áp dụng.',true); return; }
            if (window && (!target || !/^\d{2}:\d{2}$/.test(window.start) || !/^\d{2}:\d{2}$/.test(window.end) || window.end <= window.start)) { notice('Khung giờ đề xuất chưa hợp lệ. Hãy yêu cầu AI đề xuất lại.',true); return; }
            const updates = selected.map(day => {
                const suggested = day.items.map((item,index) => ({item,index})).filter(({item}) => !window || item.start_time >= window.start && item.end_time <= window.end);
                const items = suggested.map(({item,index}) => ({item_type:item.kind === 'place' ? (item.item_type || 'attraction') : 'activity',title:item.title,place_name:item.address || '',description:description(item.description) || '',
                    provider:item.provider,...(item.provider==='google_sheet'?{maps_url:item.maps_url,maps_link_type:item.maps_link_type,price_reference:item.price_reference}:{}),
                    start_time:item.start_time || null,end_time:item.end_time || null,source_place_id:item.source_place_id || null,end_day_offset:item.end_day_offset || 0,opening_check:{...item.opening_check,display:item.opening_display || item.opening_check?.display},estimated_cost:item.ticket_estimate?.amount ?? null,image:item.image || null,coordinates:item.coordinates || null,operating_status:item.operating_status || 'unknown',
                    ...(item.kind === 'place' && Number.isInteger(message.serverMessageId) ? {photo_reference:{message_id:message.serverMessageId,day_number:day.day_number,item_index:index}} : {})}));
                if (window) {
                    items.push(...trip.days[day.day_number-1].items.filter(item => !item.start_time || !item.end_time || item.end_time <= window.start || item.start_time >= window.end));
                    items.sort((a,b) => (a.start_time || '99:99').localeCompare(b.start_time || '99:99'));
                }
                return {day:day.day_number,items};
            });
            if (updates.some(day => day.items.length > 50)) { notice('Đề xuất vượt quá 50 hoạt động mỗi ngày. Bạn hãy giảm bớt hoạt động trước khi áp dụng.',true); return; }
            if (updates.some(day => day.items.some(item => excluded(item)))) { notice('Đề xuất có địa điểm đã được báo đóng cửa. Hãy gửi yêu cầu mới để AI chọn điểm khác.',true); return; }
            remember();
            if (timeline.planning_area !== undefined) trip.planning_area = timeline.planning_area;
            if(timeline.planning_metadata?.policy==='fixed_slots' && Number.isInteger(message.serverMessageId))trip.planning_reference={message_id:message.serverMessageId};
            if(timeline.pace)trip.pace=timeline.pace;if(timeline.interests)trip.interests=timeline.interests;
            if (!target) {
                trip.days = Array.from({length:duration}, () => ({items:[]})); trip.duration_days = duration;
                if(timeline.visit_start_date){trip.visit_start_date=timeline.visit_start_date;trip.provisional=false;}
                if (trip.dates) trip.dates = Array.from({length:duration}, (_,i) => trip.dates[i] ?? null);
            }
            for (const day of updates) trip.days[day.day-1] = {items:day.items};
            changed(); fillForm(); message.plannerDecision = 'Đã áp dụng đề xuất. Bạn có thể hoàn tác hoặc lưu lịch trình.';
            box.replaceChildren(node('p','',message.plannerDecision)); box.dataset.resolved = 'true'; notice('Đã áp dụng đề xuất cho ' + label + '.');
        },'trip-button trip-button-primary');
        apply.disabled = message.plannerVersion === undefined || !trip || trip.readOnly;
        apply.hidden = message.plannerVersion === undefined;
        const skip = button('Bỏ qua', () => { message.plannerDecision = 'Đã bỏ qua đề xuất. Lịch trình được giữ nguyên.'; box.replaceChildren(node('p','',message.plannerDecision)); box.dataset.resolved = 'true'; });
        controls.append(apply,skip); box.append(controls); return box;
    }
    window.AurelPlanner = {context, description, mapUrl, excluded, visitDate:()=>trip?.visit_start_date || '',
        canAddPlace:()=>Boolean(trip && !trip.readOnly && !busy),
        addPlace:(item,date)=>{
            if(!trip || trip.readOnly || busy)return;let day=0;
            if(date && trip.visit_start_date){day=Math.round((Date.parse(date+'T00:00:00Z')-Date.parse(trip.visit_start_date+'T00:00:00Z'))/86400000);if(day<0 || day>=trip.duration_days){notice('Ngày ghé đã chọn nằm ngoài lịch này. Hãy đổi ngày bắt đầu hoặc số ngày trước khi thêm.',true);return;}}
            const id=item.source_place_id || Number(String(item.id || '').replace(/^sheet:/,''));editActivity(day,null,{...item,title:item.name,place_name:item.address || '',source_place_id:id,start_time:'09:00',end_time:'10:00',pending_visit_start_date:date || null});
        },
        hoursForPlace:(id,date)=>api('/v2/itineraries/places/'+id+'/hours'+(date?'?date='+encodeURIComponent(date):'')),
        version:() => version, proposal, example:kind => ({destinations:'Gợi ý thêm điểm tham quan tại ' + trip?.destination,
        places:'Gợi ý địa điểm tham quan tại ' + trip?.destination,hotels:'Gợi ý khách sạn tại ' + trip?.destination,
        timeline:'Đề xuất điều chỉnh lịch trình hiện tại',budget:'Tư vấn cân đối ngân sách cho chuyến đi này',route:'Giải thích cách sắp xếp tuyến di chuyển của lịch này'})[kind]};
    $('tripChangeInfo').addEventListener('click', () => { fillForm(); step(1); notice(); });
    $('tripReturnToBoard').addEventListener('click', () => { step(2); notice(); });
    $('tripUndo').addEventListener('click',undoEdit);
    $('tripSavedButton').addEventListener('click', () => { $('tripLibraryDialog').showModal(); loadLibrary(); });
    $('tripLibraryRefresh').addEventListener('click',()=>loadLibrary());
    $('tripLibraryMore').addEventListener('click', () => { libraryPage++; loadLibrary(true); });
    $('tripRetryDestinations').addEventListener('click',loadDestinations);
    root.querySelectorAll('[name=tripStay]').forEach(r => r.addEventListener('change',syncStay));
    root.querySelectorAll('[data-trip-close]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));
    window.addEventListener('beforeunload', e => { if (dirty) {e.preventDefault();e.returnValue='';} });
    document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey && !e.target.closest('input,textarea,select,[contenteditable=true]') && trip && undo.length) { e.preventDefault(); undoEdit(); } });
    loadDestinations();
    setInterval(()=>{if(!document.hidden && root.dataset.step==='2'){queueHours();document.dispatchEvent(new Event('angel:hours-refresh'));}},60000);
})();
