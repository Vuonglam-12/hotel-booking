<script>
(() => {
    const checkIn = document.getElementById('check-in');
    const checkOut = document.getElementById('check-out');
    const guests = document.getElementById('guests');
    const quantity = document.getElementById('room-quantity');
    const error = document.getElementById('booking-error');
    const status = document.getElementById('booking-availability');
    const estimate = document.getElementById('price-estimate');
    const stock = HolidayVietDetailRooms.stock;
    const money = value => new Intl.NumberFormat('vi-VN', {maximumFractionDigits:2}).format(Number(value)) + ' ₫';
    const escapePriceText=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    let requestSerial = 0;
    let requestController;
    const localDemo = @json(\App\Support\LocalBookingDemo::enabled());
    let demoBusy = false, bookingCreated = false, demoRequestKey = null, demoSignature = null;
    let quoteSerial = 0, quoteController;
    const previousQuotes = new Map(), priceConfirmations = new Set();
    const note = document.getElementById('booking-special-request');
    const fieldTargets = {
        check_in: ['booking-checkin-feedback', 'hv-stay-checkin-trigger', checkIn],
        check_out: ['booking-checkout-feedback', 'hv-stay-checkout-trigger', checkOut],
        num_guests: ['booking-guests-feedback', 'hv-guest-trigger', guests],
        room_type_id: ['booking-room-feedback', 'room-type-display', document.getElementById('room-select')],
        quantity: ['booking-quantity-feedback', 'room-quantity', quantity],
        special_request: ['booking-note-feedback', 'booking-special-request', note],
    };
    let serverFieldErrors = {}, serverSelection = '', submitProblem = '';
    const selectionKey = () => JSON.stringify([checkIn.value, checkOut.value, guests.value,
        document.getElementById('room-select').value, quantity.value, note?.value || '']);
    const pricing = {status:'idle', key:'', quotes:new Map(), errors:new Map(), getQuote, hasDates:validDates};
    window.HolidayVietDetailPricing = pricing;

    function apiMessage(data, fallback) {
        const message = typeof data?.message === 'string' ? data.message.trim() : '';
        // Keep the server's useful Vietnamese reason, never a framework/SQL/network trace.
        return message && /[^\x00-\x7f]/.test(message) && !/SQLSTATE|Illuminate\\|Stack trace/i.test(message) ? message : fallback;
    }

    function requestMessage(code, area, data) {
        if (code === 401 || code === 419) return 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại để tiếp tục đặt phòng.';
        if (code === 429) return 'Bạn gửi yêu cầu quá nhanh. Vui lòng chờ một lát rồi thử lại.';
        if (code === 403) return 'Tài khoản hiện chưa được phép thực hiện yêu cầu đặt phòng này. Vui lòng đăng nhập bằng tài khoản khách hoặc liên hệ hỗ trợ.';
        if (code === 404) return 'Khách sạn hoặc loại phòng đã chọn hiện không còn khả dụng. Vui lòng tải lại trang hoặc chọn khách sạn khác.';
        if (code >= 500) return area === 'booking'
            ? 'Hệ thống chưa trả được kết quả tạo đơn. Hãy kiểm tra Lịch sử đặt phòng trước khi thử lại.'
            : 'Hệ thống tạm thời chưa kiểm tra được ' + (area === 'price' ? 'giá từng đêm' : 'phòng trống') + '. Bấm “Tải lại giá và phòng trống” để thử lại.';
        return apiMessage(data, area === 'booking' ? 'Chưa tạo được đơn từ thông tin này. Vui lòng kiểm tra các mục bên dưới.'
            : area === 'price' ? 'Chưa lấy được giá cho ngày và số phòng bạn chọn. Bấm “Tải lại giá và phòng trống”.'
            : 'Chưa kiểm tra được phòng trống cho kỳ lưu trú này. Bấm “Tải lại giá và phòng trống”.');
    }

    function fieldProblems(type) {
        const fields = {};
        if (!checkIn.value) fields.check_in = 'Bạn chưa chọn ngày nhận phòng.';
        else if (checkIn.min && checkIn.value < checkIn.min) fields.check_in = 'Ngày nhận phòng đã qua. Vui lòng chọn từ hôm nay trở đi.';
        else if (!checkIn.checkValidity()) fields.check_in = 'Ngày nhận phòng chưa hợp lệ. Vui lòng chọn lại trên lịch.';
        if (!checkOut.value) fields.check_out = 'Bạn chưa chọn ngày trả phòng.';
        else if (checkIn.value && checkOut.value <= checkIn.value) fields.check_out = 'Ngày trả phòng phải sau ngày nhận phòng ít nhất 1 ngày.';
        else if (!checkOut.checkValidity()) fields.check_out = 'Ngày trả phòng chưa hợp lệ. Vui lòng chọn lại trên lịch.';
        if (!fields.check_in && !fields.check_out && validDates()) {
            const nights = (Date.parse(checkOut.value + 'T00:00:00Z') - Date.parse(checkIn.value + 'T00:00:00Z')) / 86400000;
            if (nights > 365) fields.check_out = 'Mỗi lượt đặt tối đa 365 đêm. Vui lòng chọn ngày trả phòng gần hơn.';
        }
        const count = Number(quantity.value), guestCount = Number(guests.value);
        if (!guests.value.trim()) fields.num_guests = 'Bạn chưa nhập số khách.';
        else if (Number.isSafeInteger(guestCount) && guestCount > 100) fields.num_guests = 'Mỗi lượt đặt tối đa 100 khách. Vui lòng giảm Tổng số khách.';
        else if (!guests.checkValidity() || !Number.isSafeInteger(guestCount) || guestCount < 1) fields.num_guests = 'Số khách phải là số nguyên từ 1 trở lên. Bấm Tổng số khách để chọn lại.';
        if (!type) fields.room_type_id = HolidayVietDetailRooms.types.size===0?'Khách sạn hiện chưa có loại phòng để đặt trực tuyến. Vui lòng liên hệ khách sạn hoặc chọn khách sạn khác.'
            :'Bạn chưa chọn loại phòng. Bấm “Chọn loại phòng” để xem danh sách.';
        if (!quantity.value.trim()) fields.quantity = 'Bạn chưa nhập số lượng phòng.';
        else if (!quantity.checkValidity() || !Number.isSafeInteger(count) || count < 1 || count > 10) fields.quantity = 'Số lượng phòng phải là số nguyên từ 1 đến 10.';
        if(type && !(Number(type.capacity)>0) && !fields.num_guests) fields.num_guests='Chưa có thông tin sức chứa của '+type.name+'. Vui lòng chọn loại phòng khác hoặc liên hệ khách sạn để xác nhận.';
        if (type && !fields.quantity && !fields.num_guests && Number(type.capacity) > 0 && guestCount > Number(type.capacity) * count) {
            const needed = Math.ceil(guestCount / Number(type.capacity));
            fields.num_guests = 'Bạn chọn ' + guestCount + ' khách, nhưng ' + count + ' phòng ' + type.name + ' chỉ chứa tối đa ' + (Number(type.capacity) * count)
                + ' khách. ' + (needed <= 10 ? 'Chọn ít nhất ' + needed + ' phòng, giảm số khách hoặc đổi loại phòng.' : 'Hãy giảm số khách hoặc đổi loại phòng có sức chứa phù hợp.');
        }
        if (note && (!note.checkValidity() || Array.from(note.value).length > 500)) fields.special_request = 'Yêu cầu thêm vượt 500 ký tự. Vui lòng rút ngắn nội dung.';
        return { ...fields, ...serverFieldErrors };
    }

    function paintFields(fields) {
        for (const [field, [hintId, targetId, input]] of Object.entries(fieldTargets)) {
            const hint = document.getElementById(hintId), target = document.getElementById(targetId);
            if (hint) { hint.textContent = fields[field] || ''; hint.hidden = !fields[field]; }
            for (const control of new Set([target, input])) {
                if (control) control.setAttribute('aria-invalid', fields[field] ? 'true' : 'false');
            }
        }
    }

    function paintChecklist(items) {
        error.replaceChildren();error.hidden = items.length === 0;
        if (!items.length) return;
        const heading = document.createElement('p');heading.textContent = 'Cần kiểm tra ' + items.length + ' mục trước khi tiếp tục:';
        const list = document.createElement('ul');
        for (const item of items) {
            const row = document.createElement('li'), link = document.createElement('a');
            link.href = '#' + item.target;link.textContent = item.message;
            link.addEventListener('click', event => {
                event.preventDefault();
                const target = document.getElementById(item.target);
                target?.scrollIntoView({block:'center', behavior:'smooth'});
                target?.focus({preventScroll:true});
            });
            row.appendChild(link);list.appendChild(row);
        }
        error.appendChild(heading);error.appendChild(list);
    }

    function quoteKey() { return [checkIn.value,checkOut.value,quantity.value].join('|'); }
    function getQuote(typeId) { return pricing.key === quoteKey() ? pricing.quotes.get(Number(typeId)) : undefined; }

    async function refreshQuotes(force=false) {
        if(!force&&pricing.key===quoteKey()&&pricing.status==='ready'&&pricing.errors.size===0)return;
        quoteController?.abort();
        const serial = ++quoteSerial;
        pricing.key = quoteKey();pricing.quotes.clear();pricing.errors.clear();
        if (!validDates() || !quantity.checkValidity()) { pricing.status='idle';HolidayVietDetailRooms.sync();return; }
        pricing.status='loading';quoteController=new AbortController();
        const signal=quoteController.signal;
        let timedOut=false;
        const activeController=quoteController;
        const timeout=setTimeout(()=>{timedOut=true;activeController.abort();},20000);
        const params=new URLSearchParams({check_in:checkIn.value,check_out:checkOut.value,quantity:quantity.value});
        const expectedNights=(Date.parse(checkOut.value+'T00:00:00Z')-Date.parse(checkIn.value+'T00:00:00Z'))/86400000;
        const count=Number(quantity.value);
        HolidayVietDetailRooms.sync();calcPrice();
        await Promise.allSettled(Array.from(HolidayVietDetailRooms.types.keys(),async typeId=>{
            try {
                const response=await _layoutApi('/hotels/'+HOTEL_ID+'/room-types/'+typeId+'/quote?'+params,{signal,cache:'no-store'});
                const data=await response.json().catch(()=>null);
                if(!response.ok)throw new Error(requestMessage(response.status,'price',data));
                if(!data)throw new Error('Máy chủ chưa trả được bảng giá hợp lệ. Bấm “Tải lại giá và phòng trống”.');
                if(data.quantity!==count||data.nights!==expectedNights||!Array.isArray(data.nightly_rates)||data.nightly_rates.length!==expectedNights
                    ||typeof data.quote_hash!=='string'||!data.quote_hash||!/^\d{1,10}\.\d{2}$/.test(data.total_price)
                    ||!Number.isFinite(Number(data.average_nightly_price)))throw new Error('Chưa xác định được bảng giá cho kỳ lưu trú này.');
                if(serial!==quoteSerial)return;
                const identity=pricing.key+'|'+typeId,previous=previousQuotes.get(identity);
                if(previous&&previous!==data.quote_hash){priceConfirmations.add(identity);document.getElementById('booking-price-accept').checked=false;}
                previousQuotes.set(identity,data.quote_hash);
                pricing.quotes.set(typeId,data);
                // Paint each completed quote immediately; another room type must not delay this selection.
                HolidayVietDetailRooms.sync();calcPrice();
            }catch(exception){
                if(serial===quoteSerial&&(exception.name!=='AbortError'||timedOut))pricing.errors.set(typeId,
                    timedOut?'Kiểm tra giá mất quá lâu. Bấm “Tải lại giá và phòng trống” để thử lại.':exception instanceof TypeError?'Không kết nối được để lấy giá. Kiểm tra mạng rồi bấm “Tải lại giá và phòng trống”.':exception.message||'Chưa lấy được giá cho kỳ lưu trú này. Vui lòng tải lại.');
                if(serial===quoteSerial){HolidayVietDetailRooms.sync();calcPrice();}
            }
        }));
        clearTimeout(timeout);
        if(serial!==quoteSerial)return;
        pricing.status='ready';HolidayVietDetailRooms.sync();calcPrice();
    }

    function validDates() {
        return checkIn.value && checkOut.value && checkIn.checkValidity()
            && checkOut.checkValidity() && checkOut.value > checkIn.value;
    }

    function updateDateLimits() {
        if (checkIn.value) {
            const next = new Date(checkIn.value + 'T00:00:00Z');
            next.setUTCDate(next.getUTCDate() + 1);
            checkOut.min = next.toISOString().slice(0, 10);
        } else checkOut.min = checkIn.min;
    }

    function calcPrice() {
        const type = HolidayVietDetailRooms.types.get(Number(document.getElementById('room-select').value));
        document.getElementById('change-room-selection').hidden = !type;
        document.getElementById('booking-choose-room').hidden = !!type||HolidayVietDetailRooms.types.size===0;
        if(serverSelection&&serverSelection!==selectionKey()){
            serverFieldErrors={};serverSelection='';submitProblem='';
            document.getElementById('booking-submit-error').hidden=true;
            document.getElementById('booking-submit-action').hidden=true;
        }
        const count = Number(quantity.value);
        const quote = type ? getQuote(type.id) : null;
        const fields=fieldProblems(type);
        const problems=Object.entries(fields).map(([field,message])=>({message,target:fieldTargets[field][1]}));
        const priceFailure=type&&validDates()&&pricing.key===quoteKey()?pricing.errors.get(type.id):null;
        if (validDates() && stock.status === 'error') problems.push({message:stock.error,target:'booking-price-retry'});
        if (priceFailure) problems.push({message:priceFailure,target:'booking-price-retry'});
        estimate.hidden = true;
        baseTotal = 0;
        currentTotal = 0;
        if (type) {
            if(validDates()&&stock.status==='ready'&&stock.counts.has(type.id)&&!fields.quantity){
                const available=stock.counts.get(type.id);
                if(available<count){
                    fields.quantity=available===0?'Loại phòng '+type.name+' đã hết phòng trong khoảng ngày bạn chọn. Hãy đổi ngày hoặc loại phòng.'
                        :'Bạn chọn '+count+' phòng nhưng chỉ còn '+available+' phòng '+type.name+' trong toàn bộ kỳ lưu trú. Hãy giảm số phòng, đổi ngày hoặc loại phòng.';
                    problems.push({message:fields.quantity,target:available===0?'hv-stay-checkin-trigger':'room-quantity'});
                }
            }
            if (selectedRoomTypes[type.id]) selectedRoomTypes[type.id].quantity = count;
        }
        paintFields(fields);
        if (type && validDates() && quantity.checkValidity() && quote) {
            const nights = quote.nights;
            baseTotal = Number(quote.total_price);
            currentTotal = baseTotal;
            document.getElementById('booking-unit-price').textContent = money(quote.average_nightly_price);
            document.getElementById('booking-priced-room').textContent = type.name;
            document.getElementById('booking-room-quantity').textContent = count + ' phòng';
            document.getElementById('booking-nights').textContent = nights + ' đêm';
            document.getElementById('total-price').textContent = money(baseTotal);
            document.getElementById('price-subtotal').textContent = money(quote.room_subtotal??baseTotal);
            document.getElementById('price-label-nights').textContent = 'Tổng giá ' + nights + ' đêm × ' + count + ' phòng. Không tính đêm của ngày trả phòng.';
            const breakdown=document.getElementById('booking-nightly-rates');breakdown.replaceChildren();
            for(const night of quote.nightly_rates){
                const row=document.createElement('tr');
                const lineTotal=Math.round(Number(night.unit_price)*100)*count/100;
                const surcharge=Number(night.surcharge)>0?'<small class="hv-price-surcharge">Phụ thu '+(night.source==='day'?'ngày':night.source==='season'?'mùa':'')+' +'+escapePriceText(money(night.surcharge))+'</small>':'';
                row.innerHTML='<td><span class="hv-price-date">'+escapePriceText(night.date.split('-').reverse().join('/'))+'</span></td><td><span class="hv-price-unit">'+escapePriceText(money(night.unit_price))+'</span><span class="hv-price-multiplier">× '+count+' phòng</span>'+surcharge+'</td><td><span class="hv-price-amount">'+escapePriceText(money(lineTotal))+'</span></td>';
                breakdown.appendChild(row);
            }
            document.getElementById('booking-surcharge-note').hidden=!quote.nightly_rates.some(night=>Number(night.surcharge)>0);
            estimate.hidden = false;
        } else if(type && validDates() && quantity.checkValidity() && !pricing.errors.has(type.id)) {
            document.getElementById('price-label-nights').textContent='Đang kiểm tra giá từng đêm…';
            document.getElementById('booking-unit-price').textContent='—';document.getElementById('total-price').textContent='—';
            document.getElementById('price-subtotal').textContent='—';document.getElementById('booking-priced-room').textContent=type.name;
            document.getElementById('booking-room-quantity').textContent=count+' phòng';document.getElementById('booking-nights').textContent='—';
            document.getElementById('booking-nightly-rates').replaceChildren();estimate.hidden=false;
            document.getElementById('booking-surcharge-note').hidden=true;
        }
        paintChecklist(problems);
        document.getElementById('booking-price-retry').hidden=!(type&&pricing.errors.has(type.id))&&stock.status!=='error';
        const needsConfirmation=!!type&&priceConfirmations.has(quoteKey()+'|'+type.id);
        document.getElementById('booking-price-confirmation').hidden=!needsConfirmation;
        const hasStock=!!type&&stock.status==='ready'&&stock.counts.has(type.id)&&stock.counts.get(type.id)>=count;
        const submit=document.getElementById('submit-booking-btn');
        submit.disabled = !localDemo || demoBusy || bookingCreated || needsConfirmation || !type || !validDates()
            || !quote || problems.length > 0 || !hasStock;
        let state='incomplete',summary;
        if(bookingCreated){state='created';summary='Đã tạo đơn. Đang chuyển bạn đến trang thanh toán…';}
        else if(demoBusy){state='submitting';summary='Đang tạo đơn từ thông tin bạn vừa xác nhận. Vui lòng chờ, không bấm đặt lại.';}
        else if(problems.length){
            state=Object.keys(fields).length?'incomplete':'error';
            summary=!checkIn.value&&!checkOut.value?'Bạn chưa chọn ngày nhận phòng và ngày trả phòng.':problems[0].message;
        }
        else if(submitProblem){state='error';summary=submitProblem;}
        else if(!localDemo){state='unavailable';summary='Đặt phòng trực tuyến hiện chưa mở. Vui lòng liên hệ khách sạn để đặt phòng.';}
        else if(stock.status==='loading'||!hasStock){state='checking-rooms';summary='Đang kiểm tra '+count+' phòng '+type.name+' từ '+checkIn.value.split('-').reverse().join('/')+' đến '+checkOut.value.split('-').reverse().join('/')+'. Vui lòng chờ.';}
        else if(!quote){state='checking-price';summary='Đang lấy giá từng đêm cho '+count+' phòng '+type.name+'. Vui lòng chờ báo giá trước khi đặt.';}
        else if(needsConfirmation){state='price-changed';summary='Giá đã thay đổi. Xem Chi tiết giá và đánh dấu xác nhận giá mới để tiếp tục.';}
        else if(!localStorage.getItem('token')){state='login-required';summary='Đã có giá và đủ phòng. Bạn cần đăng nhập; bấm Tiếp tục đặt phòng để đăng nhập.';}
        else{state='ready';summary='Đã đủ thông tin, có giá và đủ '+count+' phòng. Bạn có thể tiếp tục đặt phòng.';}
        status.textContent=summary;status.dataset.state=state;
        status.dataset.availability=state==='ready'||state==='created'?'success':state==='error'?'danger':state.startsWith('checking')||state==='submitting'?'loading':'idle';
        document.getElementById('booking-next-step').textContent=problems.length?'Chưa thể tiếp tục: '+problems.length+' mục cần bổ sung hoặc điều chỉnh. Bấm từng dòng trong danh sách bên trên để tới đúng mục.':summary;
    }

    if (localDemo) {
        const message = document.getElementById('booking-submit-error');
        const submit = document.getElementById('submit-booking-btn');
        const action = document.getElementById('booking-submit-action');
        function failure(text, href, label) {
            submitProblem=text;
            message.textContent=text;message.hidden=false;
            action.replaceChildren();action.hidden=!href;
            if(href){const link=document.createElement('a');link.href=href;link.textContent=label;link.className='hv-booking-change';action.appendChild(link);}
            message.focus({preventScroll:true});message.scrollIntoView({block:'nearest',behavior:'smooth'});
        }
        submit.addEventListener('click', async () => {
            if (demoBusy || submit.disabled) return;
            if (!localStorage.getItem('token')) { location.assign('/login?redirect=' + encodeURIComponent(location.pathname + location.search)); return; }
            if (!note.reportValidity()) {calcPrice();note.focus();return;}
            const type = HolidayVietDetailRooms.types.get(Number(document.getElementById('room-select').value));
            const quote=getQuote(type.id);if(!quote){calcPrice();return;}
            const payload = {hotel_id:HOTEL_ID,room_type_id:type.id,quantity:Number(quantity.value),num_guests:Number(guests.value),check_in:checkIn.value,check_out:checkOut.value,payment_method:'demo',special_request:note.value.trim(),quote_hash:quote.quote_hash};
            const signature = JSON.stringify(payload);
            if (signature !== demoSignature) { demoSignature = signature;demoRequestKey = crypto.randomUUID ? crypto.randomUUID() : Array.from(crypto.getRandomValues(new Uint8Array(16)),x=>x.toString(16).padStart(2,'0')).join(''); }
            const requestSelection=selectionKey();
            demoBusy = true;submitProblem='';submit.setAttribute('aria-busy','true');submit.textContent = 'Đang tạo đơn…';message.hidden = true;action.hidden=true;calcPrice();
            try {
                const response = await fetch('/api/bookings',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json',Authorization:'Bearer '+localStorage.getItem('token'),'Idempotency-Key':demoRequestKey},body:JSON.stringify(payload)});
                const data = await response.json().catch(()=>null);
                if (!response.ok) {
                    serverSelection=requestSelection;
                    const fallbacks={check_in:'Ngày nhận phòng chưa hợp lệ. Vui lòng chọn lại từ hôm nay trở đi.',check_out:'Ngày trả phòng phải sau ngày nhận phòng, trong tối đa 365 đêm.',
                        num_guests:'Số khách không phù hợp với loại phòng và số phòng đã chọn. Vui lòng điều chỉnh Tổng số khách.',
                        quantity:'Số lượng phòng phải từ 1 đến 10 và không vượt số phòng còn trống.',room_type_id:'Loại phòng đã chọn hiện không nhận đặt. Vui lòng chọn loại phòng khác.',special_request:'Yêu cầu thêm tối đa 500 ký tự. Vui lòng rút ngắn nội dung.'};
                    for(const [field,values] of Object.entries(data?.errors||{})){
                        if(!fieldTargets[field])continue;
                        const value=Array.isArray(values)?values[0]:values;
                        serverFieldErrors[field]=apiMessage({message:value},fallbacks[field]);
                    }
                    // Refresh conflicts/stock errors for review, without creating another order automatically.
                    if(response.status===409||response.status===422)await refreshAvailability();
                    const text=requestMessage(response.status,'booking',data);
                    const needsLogin=response.status===401||response.status===419;
                    const reviewHistory=needsLogin||response.status===409||response.status>=500||/đơn chờ thanh toán|các đơn chờ thanh toán/i.test(text);
                    failure(text,reviewHistory?(needsLogin?'/login?redirect='+encodeURIComponent(location.pathname+location.search):'/bookings'):null,needsLogin?'Đăng nhập lại':'Mở Lịch sử đặt phòng');
                    return;
                }
                if (!/^\/payments\/bookings\/\d+$/.test(data?.payment_url || '')) {
                    serverSelection=requestSelection;
                    failure('Hệ thống đã nhận yêu cầu nhưng chưa mở được trang thanh toán. Hãy kiểm tra Lịch sử đặt phòng trước khi đặt lại.','/bookings','Mở Lịch sử đặt phòng');
                    return;
                }
                bookingCreated=true;
                location.assign(data.payment_url);
            } catch (exception) {
                serverSelection=requestSelection;
                failure('Kết nối bị gián đoạn nên chưa xác định được kết quả tạo đơn. Hãy mở Lịch sử đặt phòng để kiểm tra trước khi thử lại.','/bookings','Mở Lịch sử đặt phòng');
            }
            finally { demoBusy = false;submit.removeAttribute('aria-busy');submit.textContent = bookingCreated?'Đang mở trang thanh toán…':'Tiếp tục đặt phòng';calcPrice(); }
        });
    }

    async function refreshAvailability() {
        updateDateLimits();
        refreshQuotes().catch(()=>{});
        requestController?.abort();
        const serial = ++requestSerial;
        stock.counts.clear();
        stock.error = '';
        if (!validDates()) {
            stock.status = 'idle';
            HolidayVietDetailRooms.sync();
            calcPrice();
            return;
        }
        stock.status = 'loading';
        HolidayVietDetailRooms.sync();
        calcPrice();
        requestController = new AbortController();
        const activeController=requestController;
        let timedOut=false;
        const timeout=setTimeout(()=>{timedOut=true;activeController.abort();},20000);
        const params = new URLSearchParams({ check_in: checkIn.value, check_out: checkOut.value });
        try {
            // Keep stock for every type so switching rooms never treats an unqueried type as sold out.
            // Capacity is checked above for the selection and authoritatively when creating the booking.
            const response = await _layoutApi('/hotels/' + HOTEL_ID + '/rooms?' + params, { signal: activeController.signal });
            const rows = await response.json().catch(()=>null);
            if (!response.ok) throw new Error(requestMessage(response.status,'rooms',rows));
            if (!Array.isArray(rows)) throw new Error('Dữ liệu phòng trống chưa hợp lệ. Bấm “Tải lại giá và phòng trống”.');
            if (serial !== requestSerial) return;
            for (const id of HolidayVietDetailRooms.types.keys()) {
                const row = rows.find(room => Number(room.hotel_id) === HOTEL_ID && Number(room.room_type_id) === id);
                const value = row ? Number(row.available_rooms) : 0;
                if (!Number.isSafeInteger(value) || value < 0) throw new Error('Chưa xác định được số phòng trống. Bấm “Tải lại giá và phòng trống”.');
                stock.counts.set(id, value);
            }
            stock.status = 'ready';
        } catch (exception) {
            if ((exception.name === 'AbortError'&&!timedOut) || serial !== requestSerial) return;
            stock.status = 'error';
            stock.error = timedOut?'Kiểm tra phòng trống mất quá lâu. Bấm “Tải lại giá và phòng trống” để thử lại.'
                :exception instanceof TypeError?'Không kết nối được để kiểm tra phòng trống. Kiểm tra mạng rồi bấm “Tải lại giá và phòng trống”.':exception.message||'Chưa kiểm tra được phòng trống cho ngày bạn chọn. Vui lòng tải lại.';
            stock.counts.clear();
        }finally{clearTimeout(timeout);}
        if (serial === requestSerial) { HolidayVietDetailRooms.sync(); calcPrice(); }
    }

    [checkIn, checkOut].forEach(field => field.addEventListener('change', refreshAvailability));
    let inputTimer;
    [guests, quantity].forEach(field => field.addEventListener('input', () => {
        HolidayVietDetailRooms.sync();
        calcPrice();
        clearTimeout(inputTimer);
        inputTimer = setTimeout(refreshAvailability, 250);
    }));
    note?.addEventListener('input',calcPrice);
    window.calcPrice = calcPrice;
    document.getElementById('booking-price-accept').addEventListener('change',()=>{
        const typeId=Number(document.getElementById('room-select').value);
        if(document.getElementById('booking-price-accept').checked&&getQuote(typeId))priceConfirmations.delete(quoteKey()+'|'+typeId);
        calcPrice();
    });
    window.refreshHotelAvailability = refreshAvailability;
    window.addEventListener?.('focus',()=>{if(validDates())refreshQuotes(true).catch(()=>{});});
    window.addEventListener?.('pageshow',event=>{if(event.persisted&&validDates())refreshQuotes(true).catch(()=>{});});
    window.addEventListener?.('storage',event=>{
        if(event.key!=='angel.room-pricing.changed'||!event.newValue||!validDates())return;
        try{if(Number(JSON.parse(event.newValue).hotel_id)===Number(HOTEL_ID))refreshQuotes(true).catch(()=>{});}catch{}
    });
    document.getElementById('booking-price-retry').addEventListener('click',refreshAvailability);
    const requestedType=HolidayVietDetailRooms.types.get(Number(new URLSearchParams(location.search).get('room_type_id')));
    if(requestedType)HolidayVietDetailRooms.select(requestedType);
    else refreshAvailability();
})();


// Angel Hotel Detail date range picker. Keeps #check-in / #check-out as the authoritative values.
(() => {
    const overlay = document.getElementById('hv-stay-date-dialog');
    const checkIn = document.getElementById('check-in');
    const checkOut = document.getElementById('check-out');
    if (!overlay || !checkIn || !checkOut) return;
    document.body.appendChild(overlay);
    const $ = id => document.getElementById(id);
    const checkInTrigger = $('hv-stay-checkin-trigger');
    const checkOutTrigger = $('hv-stay-checkout-trigger');
    const months = $('hv-stay-months');
    const error = $('hv-stay-error');
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
    const firstMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    const fmtISO = date => `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
    const formatLabel = value => value ? value.split('-').reverse().join('/') : 'Chọn ngày';
    const nextDay = value => { const [y,m,d] = value.split('-').map(Number); return fmtISO(new Date(y,m-1,d+1)); };
    let shown = new Date(firstMonth.getFullYear(),firstMonth.getMonth(),1);
    let draftIn = '';
    let draftOut = '';
    let editing = 'check_in';
    let returnFocus = null;
    function renderLabels() {
        $('hv-stay-checkin-label').textContent = formatLabel(checkIn.value);
        $('hv-stay-checkout-label').textContent = formatLabel(checkOut.value);
    }
    function draw() {
        $('hv-stay-prev').disabled = shown.getTime() <= firstMonth.getTime();
        $('hv-stay-draft-checkin').textContent = formatLabel(draftIn);
        $('hv-stay-draft-checkout').textContent = formatLabel(draftOut);
        $('hv-stay-select-checkin').classList.toggle('active',editing==='check_in');
        $('hv-stay-select-checkout').classList.toggle('active',editing==='check_out');
        $('hv-stay-dialog-hint').textContent = editing === 'check_in' ? 'Chọn ngày nhận phòng, sau đó chọn ngày trả phòng.' : 'Chọn ngày trả phòng sau ngày nhận phòng.';
        months.replaceChildren();
        const numberMonths = window.matchMedia('(max-width:640px)').matches ? 1 : 2;
        for(let offset=0;offset<numberMonths;offset++) {
            const first = new Date(shown.getFullYear(), shown.getMonth()+offset,1);
            const month = document.createElement('div'); month.className='hv-stay-month';
            const heading=document.createElement('h3'); heading.textContent=`Tháng ${first.getMonth()+1} Năm ${first.getFullYear()}`; month.appendChild(heading);
            const grid=document.createElement('div'); grid.className='hv-stay-days';
            ['CN','T2','T3','T4','T5','T6','T7'].forEach(label=>{const elem=document.createElement('span');elem.className='hv-stay-dow';elem.textContent=label;grid.appendChild(elem);});
            for(let n=0;n<first.getDay();n++){const blank=document.createElement('span');blank.className='hv-stay-blank';grid.appendChild(blank);}
            const count=new Date(first.getFullYear(),first.getMonth()+1,0).getDate();
            for(let day=1;day<=count;day++) {
                const date=fmtISO(new Date(first.getFullYear(),first.getMonth(),day));
                const button=document.createElement('button'); button.type='button'; button.className='hv-stay-day'; button.textContent=String(day);
                button.disabled=date<today || (editing==='check_out' && Boolean(draftIn) && date<=draftIn);
                button.setAttribute('aria-label',formatLabel(date));
                if(date===draftIn || date===draftOut) button.classList.add('selected');
                else if(draftIn && draftOut && date>draftIn && date<draftOut) button.classList.add('in-range');
                button.addEventListener('click',()=>{
                    if(editing==='check_in' || !draftIn) { draftIn=date; draftOut=''; editing='check_out'; }
                    else { draftOut=date; editing='check_in'; }
                    error.hidden=true; draw();
                });
                grid.appendChild(button);
            }
            month.appendChild(grid);months.appendChild(month);
        }
    }
    function open(boundary) {
        draftIn=checkIn.value>=today?checkIn.value:'';
        draftOut=checkOut.value>draftIn?checkOut.value:'';
        editing=boundary==='check_out' && draftIn?'check_out':'check_in';
        const base=draftIn||today; const [year,month]=base.split('-').map(Number);
        shown=new Date(year,month-1,1);
        error.hidden=true;
        overlay.hidden=false;
        returnFocus=boundary==='check_out'?checkOutTrigger:checkInTrigger;
        checkInTrigger.setAttribute('aria-expanded','true');
        checkOutTrigger.setAttribute('aria-expanded','true');
        draw(); $('hv-stay-close').focus();
    }
    function close() {
        overlay.hidden=true;
        checkInTrigger.setAttribute('aria-expanded','false');
        checkOutTrigger.setAttribute('aria-expanded','false');
        returnFocus?.focus();
    }
    function commit() {
        checkIn.value=draftIn; checkOut.value=draftOut;
        checkOut.min=draftIn?nextDay(draftIn):(checkIn.min||today);
        renderLabels();
        // The existing sidebar-script listens for change and refreshes availability/price.
        checkIn.dispatchEvent(new Event('change',{bubbles:true}));
        close();
    }
    checkInTrigger.addEventListener('click',()=>open('check_in'));
    checkOutTrigger.addEventListener('click',()=>open('check_out'));
    $('hv-stay-close').addEventListener('click',close);
    overlay.addEventListener('click',e=>{if(e.target===overlay)close();});
    document.addEventListener('keydown',e=>{if(overlay.hidden)return; if(e.key==='Escape'){e.preventDefault();close();} else if(e.key==='Tab') {const focusable=Array.from(overlay.querySelectorAll('button:not(:disabled)'));const i=focusable.indexOf(document.activeElement);if(e.shiftKey&&i===0){e.preventDefault();focusable.at(-1).focus();}else if(!e.shiftKey&&i===focusable.length-1){e.preventDefault();focusable[0].focus();}}});
    $('hv-stay-prev').addEventListener('click',()=>{shown=new Date(shown.getFullYear(),shown.getMonth()-1,1);draw();});
    $('hv-stay-next').addEventListener('click',()=>{shown=new Date(shown.getFullYear(),shown.getMonth()+1,1);draw();});
    $('hv-stay-select-checkin').addEventListener('click',()=>{editing='check_in';draw();});
    $('hv-stay-select-checkout').addEventListener('click',()=>{editing=draftIn?'check_out':'check_in';draw();});
    $('hv-stay-clear').addEventListener('click',()=>{draftIn='';draftOut='';editing='check_in';error.hidden=true;draw();});
    $('hv-stay-done').addEventListener('click',()=>{
        if(draftIn&&!draftOut){error.textContent='Bạn chưa chọn ngày trả phòng. Hãy chọn một ngày sau ngày nhận phòng trên lịch.';error.hidden=false;return;}
        if(!draftIn&&draftOut){error.textContent='Bạn chưa chọn ngày nhận phòng. Hãy chọn ngày nhận phòng trước.';error.hidden=false;return;}
        if(draftIn&&draftIn<today){error.textContent='Ngày nhận phòng đã qua. Hãy chọn từ hôm nay trở đi.';error.hidden=false;return;}
        if(draftIn&&draftOut<=draftIn){error.textContent='Ngày trả phòng phải sau ngày nhận phòng ít nhất 1 ngày.';error.hidden=false;return;}
        commit();
    });
    checkIn.addEventListener('change',renderLabels);
    checkOut.addEventListener('change',renderLabels);
    renderLabels();
})();

async function toggleWishlistDetail(hotelId, button) {
    if (!localStorage.getItem('token')) { window.requestCustomerLogin('Vui lòng đăng nhập để lưu khách sạn yêu thích.'); return; }
    if (button.disabled) return;
    button.disabled = true;
    try {
        const response = await _layoutApi('/wishlist/' + hotelId + '/toggle', { method: 'POST' });
        if(response.status===401){localStorage.removeItem('token');localStorage.removeItem('user');window.requestCustomerLogin('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');return;}
        const data = await response.json();
        if (!response.ok) throw new Error(getApiErrorMessage(data));
        if(typeof data.liked!=='boolean')throw new Error('Chưa xác định được trạng thái yêu thích.');
        (button.querySelector('[data-wishlist-label]')||button).textContent = data.liked ? 'Đã lưu yêu thích' : 'Lưu yêu thích';
        button.setAttribute('aria-pressed', String(Boolean(data.liked)));
        showToast(data.message || 'Đã cập nhật yêu thích');
    } catch (exception) { showToast(exception.message || 'Không cập nhật được yêu thích.'); }
    finally { button.disabled = false; }
    }

async function refreshWishlistDetail() {
    const button=document.getElementById('hotel-detail-wishlist');
    if(!button||!localStorage.getItem('token'))return;
    button.disabled=true;
    try {
        const response=await _layoutApi('/wishlist');
        if(!response.ok)return;
        const rows=await response.json();
        if(!Array.isArray(rows))return;
        const liked=rows.some(row=>Number(row.hotel_id)===Number(HOTEL_ID));
        (button.querySelector('[data-wishlist-label]')||button).textContent=liked?'Đã lưu yêu thích':'Lưu yêu thích';button.setAttribute('aria-pressed',String(liked));
    } finally { button.disabled=false; }
}
refreshWishlistDetail().catch(()=>{});
window.addEventListener('message',event=>{if(event.origin===location.origin&&event.data?.type==='aurel-authenticated')refreshWishlistDetail().catch(()=>{});});


    /* Angel — Hotel Detail guest popover */
    (() => {
        const overlay = document.getElementById('hv-guest-overlay');
        const trigger = document.getElementById('hv-guest-trigger');
        const input = document.getElementById('guests');
        const summary = document.getElementById('hv-guest-summary');

        if (!overlay || !trigger || !input || !summary) return;

        document.body.appendChild(overlay);

        const parsedInitial = Number(input.value);
        const initial = Number.isSafeInteger(parsedInitial) && parsedInitial > 0
            ? parsedInitial
            : 2;
        const MAX_GUESTS = 20;
        const counts = {
            adults: initial,
            children: 0,
            infants: 0
        };
        const parameters=new URLSearchParams(location.search);
        const requestedGroups={adults:Number(parameters.get('adults')),children:Number(parameters.get('children')),infants:Number(parameters.get('infants'))};
        if(Object.values(requestedGroups).every(Number.isSafeInteger)&&requestedGroups.adults>=1&&requestedGroups.children>=0&&requestedGroups.infants>=0
            &&requestedGroups.adults+requestedGroups.children+requestedGroups.infants===initial&&initial<=MAX_GUESTS)Object.assign(counts,requestedGroups);
        let draft = { ...counts };
        const total = values =>
            values.adults + values.children + values.infants;

        function draw() {
            overlay.querySelectorAll('.hv-guest-row').forEach(row => {
                const kind = row.dataset.kind;
                const value = draft[kind];
                row.querySelector('[data-count]').textContent = value;
                row.querySelector('[data-step="-1"]').disabled =
                    value <= (kind === 'adults' ? 1 : 0);
                row.querySelector('[data-step="1"]').disabled =
                    total(draft) >= MAX_GUESTS;
            });
        }

        function positionPopover() {
            const rect = trigger.getBoundingClientRect();
            const viewportWidth = document.documentElement.clientWidth;
            const viewportHeight = window.innerHeight;
            const edge = 8;
            const width = Math.min(
                Math.max(rect.width, Math.min(280, viewportWidth - edge * 2)),
                360,
                viewportWidth - edge * 2
            );
            const left = Math.max(edge, Math.min(rect.left, viewportWidth - width - edge));

            overlay.style.width = `${width}px`;
            overlay.style.left = `${left}px`;
            overlay.style.top = `${rect.bottom + edge}px`;
            overlay.style.maxHeight = `${Math.max(0, viewportHeight - rect.bottom - edge * 2)}px`;
        }

        function open() {
            draft = { ...counts };
            draw();
            overlay.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            positionPopover();
            overlay.querySelector('button:not(:disabled)')?.focus();
        }

        function close(restoreFocus = false) {
            overlay.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus) trigger.focus();
        }

        function save() {
            counts.adults = draft.adults;
            counts.children = draft.children;
            counts.infants = draft.infants;

            const count = total(counts);

            input.value = String(count);
            summary.textContent = `${count} khách`;

            // Báo cho logic Booking hiện tại tính lại.
            input.dispatchEvent(new Event('input', {
                bubbles: true
            }));

            close(true);
        }

        trigger.addEventListener('click', () => {
            if (overlay.hidden) open();
            else close(true);
        });

        document.getElementById('hv-guest-done')
            .addEventListener('click', save);

        document.addEventListener('pointerdown', event => {
            if (!overlay.hidden && !overlay.contains(event.target) && !trigger.contains(event.target)) {
                close();
            }
        });

        overlay.querySelectorAll('[data-step]').forEach(button => {
            button.addEventListener('click', () => {
                const row = button.closest('.hv-guest-row');
                const kind = row.dataset.kind;
                const step = Number(button.dataset.step);

                const minimum = kind === 'adults' ? 1 : 0;
                const next = draft[kind] + step;

                if (next < minimum || next > MAX_GUESTS) return;
                if (step > 0 && total(draft) >= MAX_GUESTS) return;

                draft[kind] = next;
                draw();
            });
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                if (!overlay.hidden) {
                    event.preventDefault();
                    close(true);
                }
            }
        });

        document.addEventListener('scroll', () => {
            if (!overlay.hidden) positionPopover();
        }, true);
        window.addEventListener('resize', () => {
            if (!overlay.hidden) positionPopover();
        });
        window.visualViewport?.addEventListener('resize', () => {
            if (!overlay.hidden) positionPopover();
        });
        window.visualViewport?.addEventListener('scroll', () => {
            if (!overlay.hidden) positionPopover();
        });

        summary.textContent = `${total(counts)} khách`;
    })();
</script>
