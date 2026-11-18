@once
<script>
(() => {
    const pad=n=>String(n).padStart(2,'0'),dateKey=d=>`${d.getUTCFullYear()}-${pad(d.getUTCMonth()+1)}-${pad(d.getUTCDate())}`;
    const today=()=>{const d=new Date();return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;};
    const parseDate=value=>{const m=/^(\d{4})-(\d{2})-(\d{2})$/.exec(value);if(!m||+m[1]<1000||+m[1]>9999)return null;const d=new Date(Date.UTC(+m[1],+m[2]-1,+m[3]));return dateKey(d)===value?d:null;};
    const displayDate=value=>{const p=value.split('-');return p.length===3?`${p[2]}/${p[1]}/${p[0]}`:'';};
    const typedDate=value=>{const p=/^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value.trim());return p&&parseDate(`${p[3]}-${p[2]}-${p[1]}`)?`${p[3]}-${p[2]}-${p[1]}`:'';};
    const validTime=value=>/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/.test(value);
    const el=(tag,cls,text)=>{const n=document.createElement(tag);if(cls)n.className=cls;if(text!==undefined)n.textContent=text;return n;};
    let active=null;
    function mount(source){
        if(source.dataset.pickerMounted)return;source.dataset.pickerMounted='true';
        const mode=source.dataset.aurelPicker,hasDate=mode!=='time',hasTime=mode!=='date',label=source.dataset.pickerLabel||'Thời gian';
        const owner=source.closest('dialog'),fields=el('div','acp-fields'+(mode==='datetime'?'':' acp-single'));
        let dateField,timeField,dateTrigger,timeTrigger,viewYear,viewMonth,kind='',opener=null,raf=0,stagedHour=0,stagedMinute=0,stagedSeconds='';
        const panel=el('div','acp-panel');panel.id=source.id+'-picker';panel.hidden=true;panel.setAttribute('role','dialog');panel.setAttribute('popover','manual');
        (owner||document.body).append(panel);source.after(fields);source.hidden=true;
        function control(type){
            const wrap=el('div','acp-control'),input=el('input'),button=el('button','acp-trigger');input.type='text';input.inputMode='numeric';input.autocomplete='off';input.id=source.id+'-'+type;
            input.placeholder=type==='date'?'dd/mm/yyyy':'HH:mm';input.setAttribute('aria-label',label+(type==='date'?' – ngày':' – giờ (24 giờ)'));input.setAttribute('aria-haspopup','dialog');input.setAttribute('aria-controls',panel.id);input.setAttribute('aria-expanded','false');
            button.type='button';button.setAttribute('aria-label',type==='date'?'Mở lịch chọn ngày':'Mở bảng chọn giờ');button.setAttribute('aria-controls',panel.id);button.setAttribute('aria-expanded','false');
            // Static local icons; no user input is inserted as HTML.
            button.innerHTML=type==='date'?'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18"/></svg>':'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
            button.addEventListener('click',()=>toggle(type,button));input.addEventListener('keydown',event=>{if(event.key==='ArrowDown'){event.preventDefault();show(type,input);}if(event.key==='Enter'){event.preventDefault();commit();close(false);}});
            input.addEventListener('input',()=>input.setCustomValidity(''));input.addEventListener('change',commit);wrap.append(input,button);fields.append(wrap);return [input,button];
        }
        if(hasDate)[dateField,dateTrigger]=control('date');if(hasTime)[timeField,timeTrigger]=control('time');
        const clear=el('button','acp-clear',mode==='time'?'Xóa giờ':'Xóa ngày giờ');clear.type='button';clear.addEventListener('click',()=>{source.value='';source.dispatchEvent(new Event('change',{bubbles:true}));close(false);});fields.append(clear);
        function sync(){const value=source.value;const parts=value.split('T');if(dateField){dateField.value=displayDate(parts[0]||'');dateField.setCustomValidity('');}if(timeField){timeField.value=(mode==='time'?value:parts[1])||'';timeField.setCustomValidity('');}}
        function commit(){
            let date=hasDate?typedDate(dateField.value):'',time=hasTime?timeField.value.trim():'';
            if(dateField)dateField.setCustomValidity(dateField.value&&!date?'Nhập ngày hợp lệ theo dd/mm/yyyy.':'');
            if(timeField)timeField.setCustomValidity(time&&!validTime(time)?'Nhập giờ từ 00:00 đến 23:59.':'');
            if((dateField&&!dateField.checkValidity())||(timeField&&!timeField.checkValidity()))return false;
            if(mode==='datetime'&&!date&&time){dateField.setCustomValidity('Chọn ngày trước khi lưu giờ xuất bản.');return false;}
            if(mode==='datetime'&&date&&!time)time='00:00';
            source.value=mode==='datetime'?(date?date+'T'+time:''):mode==='date'?date:time;
            source.dispatchEvent(new Event('input',{bubbles:true}));source.dispatchEvent(new Event('change',{bubbles:true}));return true;
        }
        function close(focus=true){
            if(!kind)return;kind='';if(typeof panel.hidePopover==='function'&&panel.matches(':popover-open'))panel.hidePopover();panel.hidden=true;
            [dateField,timeField,dateTrigger,timeTrigger].filter(Boolean).forEach(n=>n.setAttribute('aria-expanded','false'));
            if(active===controller)active=null;if(focus&&opener?.isConnected)opener.focus({preventScroll:true});
        }
        function bounds(){const vv=window.visualViewport;let left=vv?.offsetLeft||0,top=vv?.offsetTop||0,right=left+(vv?.width||innerWidth),bottom=top+(vv?.height||innerHeight);
            if(owner){const r=owner.getBoundingClientRect();left=Math.max(left,r.left);right=Math.min(right,r.right);top=Math.max(top,r.top);bottom=Math.min(bottom,r.bottom);}return {left:left+8,right:right-8,top:top+8,bottom:bottom-8};}
        function position(){if(!kind)return;const b=bounds(),anchor=(kind==='date'?dateField:timeField).getBoundingClientRect();
            if(anchor.bottom<b.top||anchor.top>b.bottom){close(false);return;}
            const width=Math.max(0,Math.min(kind==='date'?288:252,b.right-b.left));panel.style.width=width+'px';panel.style.maxHeight='none';
            const natural=panel.scrollHeight+2,below=b.bottom-anchor.bottom-6,above=anchor.top-b.top-6,useBelow=below>=natural||below>=above;
            const height=Math.max(0,Math.min(natural,Math.max(0,useBelow?below:above)));panel.style.maxHeight=height+'px';
            const left=Math.max(b.left,Math.min(anchor.left,b.right-width)),top=useBelow?anchor.bottom+6:anchor.top-6-height;
            panel.style.left=left+'px';panel.style.top=Math.max(b.top,Math.min(top,b.bottom-height))+'px';
        }
        function schedulePosition(){if(kind&&!raf)raf=requestAnimationFrame(()=>{raf=0;position();});}
        function focusDay(key){panel.querySelectorAll('[data-date]').forEach(n=>n.tabIndex=n.dataset.date===key?0:-1);panel.querySelector(`[data-date="${key}"]`)?.focus({preventScroll:true});}
        function chooseDay(key){dateField.value=displayDate(key);if(commit())close();}
        function calendar(focusKey=null,nav=null){
            panel.replaceChildren();const heading=el('div','acp-heading'),title=el('strong','acp-title',`Tháng ${viewMonth+1} năm ${viewYear}`);
            for(const [step,text] of [[-1,'‹'],[1,'›']]){const button=el('button','acp-nav',text);button.type='button';button.dataset.nav=String(step);button.setAttribute('aria-label',step<0?'Tháng trước':'Tháng sau');button.disabled=(step<0&&viewYear===1000&&viewMonth===0)||(step>0&&viewYear===9999&&viewMonth===11);button.addEventListener('click',()=>{const d=new Date(Date.UTC(viewYear,viewMonth+step,1));viewYear=d.getUTCFullYear();viewMonth=d.getUTCMonth();calendar(null,step);});heading.append(button);if(step===-1)heading.append(title);}
            const week=el('div','acp-week');week.setAttribute('aria-hidden','true');['CN','T2','T3','T4','T5','T6','T7'].forEach(day=>week.append(el('span','',day)));
            const days=el('div','acp-days');days.setAttribute('role','group');days.setAttribute('aria-label',title.textContent);const first=new Date(Date.UTC(viewYear,viewMonth,1)),total=new Date(Date.UTC(viewYear,viewMonth+1,0)).getUTCDate(),selected=typedDate(dateField.value),now=today();
            for(let i=0;i<first.getUTCDay();i++)days.append(el('span'));
            for(let n=1;n<=total;n++){const key=`${viewYear}-${pad(viewMonth+1)}-${pad(n)}`,button=el('button','acp-day',String(n));button.type='button';button.dataset.date=key;button.setAttribute('aria-label',displayDate(key));button.setAttribute('aria-pressed',String(key===selected));if(key===now)button.setAttribute('aria-current','date');button.tabIndex=key===(focusKey||selected||now)?0:-1;
                button.addEventListener('click',()=>chooseDay(key));button.addEventListener('keydown',event=>{const deltas={ArrowLeft:-1,ArrowRight:1,ArrowUp:-7,ArrowDown:7};let d=parseDate(key);if(event.key in deltas)d.setUTCDate(d.getUTCDate()+deltas[event.key]);else if(event.key==='Home')d.setUTCDate(d.getUTCDate()-d.getUTCDay());else if(event.key==='End')d.setUTCDate(d.getUTCDate()+6-d.getUTCDay());else return;event.preventDefault();if(d.getUTCFullYear()<1000||d.getUTCFullYear()>9999)return;viewYear=d.getUTCFullYear();viewMonth=d.getUTCMonth();calendar(dateKey(d));});days.append(button);}
            if(!days.querySelector('[tabindex="0"]'))days.querySelector('button').tabIndex=0;
            const footer=el('div','acp-footer'),current=el('button','','Hôm nay');current.type='button';current.addEventListener('click',()=>chooseDay(today()));footer.append(current,el('small','','dd/mm/yyyy'));
            panel.append(heading,week,days,footer);position();if(focusKey)focusDay(focusKey);if(nav)panel.querySelector(`[data-nav="${nav}"]`).focus({preventScroll:true});
        }
        function timeChoice(){
            panel.replaceChildren();panel.append(el('div','acp-heading',label+' · 24 giờ'));const columns=el('div','acp-time-columns');
            for(const [unit,count,title] of [['hour',24,'Giờ'],['minute',60,'Phút']]){const column=el('div'),list=el('div','acp-time-list');column.append(el('div','acp-time-label',title));list.dataset.unit=unit;list.setAttribute('role','listbox');list.setAttribute('aria-label',title);
                for(let value=0;value<count;value++){const button=el('button','acp-time-option',pad(value));button.type='button';button.dataset.value=String(value);button.setAttribute('role','option');const selected=value===(unit==='hour'?stagedHour:stagedMinute);button.setAttribute('aria-selected',String(selected));button.tabIndex=selected?0:-1;
                    button.addEventListener('click',()=>{if(unit==='hour'){stagedHour=value;list.querySelectorAll('button').forEach(n=>{const on=+n.dataset.value===value;n.setAttribute('aria-selected',String(on));n.tabIndex=on?0:-1;});}else{stagedMinute=value;applyTime();}});
                    button.addEventListener('keydown',event=>{let next;if(event.key==='ArrowDown')next=(value+1)%count;else if(event.key==='ArrowUp')next=(value+count-1)%count;else if(event.key==='Home')next=0;else if(event.key==='End')next=count-1;else if(['ArrowLeft','ArrowRight'].includes(event.key)){event.preventDefault();columns.querySelector(`[data-unit="${unit==='hour'?'minute':'hour'}"] [tabindex="0"]`).focus();return;}else return;event.preventDefault();list.querySelectorAll('button').forEach(n=>n.tabIndex=+n.dataset.value===next?0:-1);list.querySelector(`[data-value="${next}"]`).focus();});list.append(button);}
                column.append(list);columns.append(column);}
            const footer=el('div','acp-footer'),done=el('button','','Xong');done.type='button';done.addEventListener('click',applyTime);footer.append(el('small','','HH:mm · 00–23 giờ'),done);panel.append(columns,footer);position();
        }
        function applyTime(){timeField.value=pad(stagedHour)+':'+pad(stagedMinute)+(stagedSeconds?':'+stagedSeconds:'');commit();close();}
        function show(type,trigger){
            if(active)active.close(false);kind=type;opener=trigger;active=controller;panel.dataset.kind=type;panel.setAttribute('aria-label',type==='date'?'Chọn ngày – '+label:'Chọn giờ – '+label);panel.hidden=false;
            if(typeof panel.showPopover==='function')panel.showPopover();
            [type==='date'?dateField:timeField,type==='date'?dateTrigger:timeTrigger].filter(Boolean).forEach(n=>n.setAttribute('aria-expanded','true'));
            if(type==='date'){const selected=typedDate(dateField.value)||today(),d=parseDate(selected);viewYear=d.getUTCFullYear();viewMonth=d.getUTCMonth();calendar();position();focusDay(selected);}
            else{const values=(validTime(timeField.value)?timeField.value:'00:00').split(':');stagedHour=+values[0];stagedMinute=+values[1];stagedSeconds=values[2]||'';timeChoice();panel.querySelectorAll('[aria-selected="true"]').forEach(n=>n.scrollIntoView({block:'nearest'}));panel.querySelector('[data-unit="hour"] [tabindex="0"]').focus({preventScroll:true});position();}
        }
        function toggle(type,trigger){if(kind===type)close();else show(type,trigger);}
        const controller={close,contains:node=>panel.contains(node)||fields.contains(node)};
        source.addEventListener('change',sync);source.form?.addEventListener('reset',()=>{close(false);setTimeout(sync,0);});owner?.addEventListener('close',()=>close(false));
        document.addEventListener('scroll',schedulePosition,true);window.addEventListener('resize',schedulePosition);window.visualViewport?.addEventListener('resize',schedulePosition);sync();
    }
    document.addEventListener('pointerdown',event=>{if(active&&!active.contains(event.target))active.close(false);},true);
    document.addEventListener('keydown',event=>{if(active&&event.key==='Escape'){event.preventDefault();event.stopPropagation();active.close();}},true);
    document.querySelectorAll('input[data-aurel-picker]').forEach(mount);
})();
</script>
@endonce
