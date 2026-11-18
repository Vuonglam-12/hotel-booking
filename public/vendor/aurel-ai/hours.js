/* Shared hours presentation. Current status comes from the local-time backend clock, never the visit time. */
(() => {
    'use strict';
    const node=(tag,cls,text)=>{const n=document.createElement(tag);if(cls)n.className=cls;if(text)n.textContent=text;return n;};
    const icon=kind=>{const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('fill','none');svg.setAttribute('stroke','currentColor');svg.setAttribute('stroke-width','1.8');svg.setAttribute('aria-hidden','true');
        for(const [tag,attrs] of kind==='clock'?[['circle',{cx:12,cy:12,r:9}],['path',{d:'M12 7v5l3 2'}]]:[['path',{d:'m7 10 5 5 5-5'}]]){const p=document.createElementNS(svg.namespaceURI,tag);for(const [key,value]of Object.entries(attrs))p.setAttribute(key,value);svg.append(p);}return svg;};
    function render(container,display,check,options={}) {
        if(display?.evaluated_at && Date.now()-Date.parse(display.evaluated_at)>120000 && display.status!=='unknown')display={...display,status:'unknown',summary:'Giờ hoạt động: Xem lịch từng thứ'};
        container.refreshPresentation=()=>render(container,display,check,options);
        options.warningTarget?.replaceChildren();
        const wasOpen=container.querySelector('details')?.open;container.replaceChildren();container.classList.add('angel-hours');
        const hasData=Boolean(display?.has_data),summaryText=hasData?display.summary:'Chưa có thông tin giờ hoạt động';
        const details=hasData?node('details','angel-hours-dropdown'):null,summary=node(hasData?'summary':'div','angel-hours-summary');
        summary.dataset.status=display?.status || 'unknown';summary.append(icon('clock'));
        const text=node('span','angel-hours-text'),parts=summaryText.split(' · ');text.append(node('span','angel-hours-state',parts.shift()));if(parts.length)text.append(document.createTextNode(' · '+parts.join(' · ')));summary.append(text);
        if(details){summary.append(icon('chevron'));details.append(summary);details.open=Boolean(wasOpen);const body=node('div','angel-hours-body');
            if(display.hours_text)body.append(node('p','','Giờ tham khảo: '+display.hours_text));
            const week=node('dl','angel-hours-week');for(const day of display.weekly || []){const row=node('div');if(day.today)row.dataset.today='true';row.append(node('dt','',day.label),node('dd','',day.hours));week.append(row);}if(week.childElementCount)body.append(week);
            for(const exception of display.exceptions || [])body.append(node('p','angel-hours-exception',exception.date.split('-').reverse().join('/')+': '+exception.hours+' (giờ đặc biệt)'));
            if(options.extra)body.append(options.extra);
            if(check?.visit_date){body.append(node('p','angel-hours-visit',check.label));if(check.message && ['closed','outside_hours','invalid_visit'].includes(check.status))body.append(node('p','',check.message));for(const suggestion of check.suggestions || [])body.append(node('p','','Gợi ý: '+suggestion.date.split('-').reverse().join('/')+' · '+suggestion.start_time+'–'+suggestion.end_time+(suggestion.end_day_offset?' (ngày hôm sau)':'')));}
            let hasDate=false,hasSource=false;for(const reference of display.references || []){
                if(reference.source_url)try{const url=new URL(reference.source_url);if(['https:','http:'].includes(url.protocol) && !url.username && !url.password){const a=node('a','','Nguồn giờ hoạt động');a.href=url.href;a.target='_blank';a.rel='noopener noreferrer';body.append(a);hasSource=true;}}catch{}
                if(reference.checked_at){const date=String(reference.checked_at);body.append(node('p','','Ngày kiểm tra nguồn: '+(/^\d{4}-\d{2}-\d{2}$/.test(date)?date.split('-').reverse().join('/'):date)));hasDate=true;}
            }
            if(!hasSource)body.append(node('p','','Chưa có liên kết nguồn giờ hoạt động.'));
            if(!hasDate)body.append(node('p','','Nguồn chưa ghi ngày cập nhật giờ hoạt động.'));
            body.append(node('p','angel-hours-reference',display.notice || 'Giờ tham khảo; cần kiểm tra lại với địa điểm trước khi đi.'));
            details.append(body);container.append(details);
        }else container.append(summary);
        if(['closed','outside_hours','place_disabled','invalid_visit'].includes(check?.status))(options.warningTarget || container).append(node('p','trip-schedule-warning angel-hours-visit-warning','Giờ ghé trong lịch chưa phù hợp. Mở chi tiết để xem gợi ý điều chỉnh.'));
    }
    window.AngelOpeningHours={render};
})();
