<script>
(() => {
    const section=document.getElementById('hotel-reviews');
    if(!section)return;
    const list=document.getElementById('hotel-review-list'),template=document.getElementById('hotel-review-card-template');
    const rating=document.getElementById('hotel-review-rating'),sort=document.getElementById('hotel-review-sort');
    const status=document.getElementById('hotel-review-status'),error=document.getElementById('hotel-review-error');
    const empty=document.getElementById('hotel-review-empty'),pagination=document.getElementById('hotel-review-pagination');
    const previous=document.getElementById('hotel-review-prev'),next=document.getElementById('hotel-review-next');
    let page=Number(section.dataset.page)||1,lastPage=Number(section.dataset.lastPage)||1,serial=0,controller;
    const number=value=>Number.isFinite(Number(value))?Number(value):0;
    const verdict=average=>average>=4.5?'Xuất sắc':average>=4?'Rất tốt':average>=3?'Tốt':average>=2?'Trung bình':'Cần cải thiện';
    const dateFormatter=new Intl.DateTimeFormat('vi-VN',{day:'2-digit',month:'2-digit',year:'numeric',timeZone:'Asia/Ho_Chi_Minh'});

    function updateSummary(stats) {
        const total=number(stats.total),average=stats.avg_overall===null?null:number(stats.avg_overall);
        section.querySelector('[data-review-overall]').textContent=average===null?'—':average.toFixed(1);
        section.querySelector('[data-review-verdict]').textContent=average===null?'Chưa có điểm đánh giá':verdict(average);
        section.querySelector('[data-review-basis]').textContent='Từ '+total+' đánh giá đã duyệt';
        const stars=section.querySelector('[data-review-summary-stars]');
        stars.style.setProperty('--review-fill',Math.max(0,Math.min(100,(average||0)*20))+'%');
        stars.setAttribute('aria-label',average===null?'Chưa có điểm':average.toFixed(1)+' trên 5 sao');
        section.querySelectorAll('[data-review-score]').forEach(button=>{
            const score=button.dataset.reviewScore,count=number(stats.rating_counts?.[score]);
            button.querySelector('[data-review-count]').textContent=count;
            button.querySelector('[data-review-bar]').style.width=(total?count/total*100:0)+'%';
            button.setAttribute('aria-label','Lọc '+score+' sao, '+count+' đánh giá');
            button.setAttribute('aria-pressed',String(rating.value===score));
        });
        let hasMetrics=false;
        section.querySelectorAll('[data-review-metric]').forEach(metric=>{
            const key=metric.dataset.reviewMetric,count=number(stats[key+'_count']),average=stats['avg_'+key];
            metric.hidden=count===0||average===null||!Number.isFinite(Number(average));
            if(metric.hidden)return;
            hasMetrics=true;
            const unit=document.createElement('small');unit.textContent='/5';
            metric.querySelector('[data-metric-value]').replaceChildren(document.createTextNode(Number(average).toFixed(1)),unit);
            metric.querySelector('[data-metric-count]').textContent=count+' lượt chấm';
        });
        document.getElementById('hotel-review-metrics').hidden=!hasMetrics;
        document.getElementById('hotel-review-toolbar').hidden=total===0;
    }

    function renderCards(reviews) {
        const fragment=document.createDocumentFragment();
        reviews.forEach(review=>{
            const card=template.content.firstElementChild.cloneNode(true);
            card.dataset.reviewId=number(review.id);
            let name=String(review.customer?.name||'Khách hàng');
            if(String(review.comment??'').startsWith('[Đánh giá mẫu] '))name=name.replace(/\s*\(Mẫu\)$/u,'');
            card.querySelector('[data-review-name]').textContent=name;
            card.querySelector('[data-review-initials]').textContent=String(name).trim().split(/\s+/).slice(0,2).map(word=>Array.from(word)[0]||'').join('').toLocaleUpperCase('vi');
            const image=card.querySelector('[data-review-avatar]');image.removeAttribute('src');image.hidden=true;
            if(review.customer?.avatar_url)try{
                const url=new URL(review.customer.avatar_url,location.origin);
                if(['http:','https:'].includes(url.protocol)){image.src=url.href;image.hidden=false;}
            }catch{}
            card.querySelector('[data-review-rating]').textContent=review.rating??'—';
            const time=card.querySelector('[data-review-date]'),date=review.created_at?new Date(review.created_at):null;
            time.hidden=!date||!Number.isFinite(date.getTime());
            if(!time.hidden){time.dateTime=date.toISOString();time.textContent=dateFormatter.format(date);}else{time.textContent='';time.removeAttribute('datetime');}
            card.querySelector('[data-review-comment]').textContent=String(review.comment??'').trim()?review.comment:'Khách hàng đã chấm điểm, chưa có nhận xét bằng lời.';
            fragment.append(card);
        });
        list.replaceChildren(fragment);
    }

    async function load(requestedPage=1) {
        controller?.abort();controller=new AbortController();
        const active=++serial,requestController=controller;
        const timeout=setTimeout(()=>requestController.abort(),12000);
        page=requestedPage;
        section.dataset.loading='true';section.setAttribute('aria-busy','true');
        previous.disabled=next.disabled=true;error.hidden=true;status.textContent='Đang tải nhận xét…';
        const params=new URLSearchParams({per_page:'6',page:String(page),sort:sort.value});
        if(rating.value)params.set('rating',rating.value);
        try{
            const response=await fetch('/api/hotels/'+Number(section.dataset.hotelId)+'/reviews?'+params,{headers:{Accept:'application/json'},signal:requestController.signal});
            if(!response.ok)throw new Error('Reviews unavailable');
            const data=await response.json();
            if(!data.stats||!Array.isArray(data.reviews?.data)||!Number.isInteger(Number(data.reviews.last_page))||Number(data.reviews.last_page)<1)throw new Error('Invalid review response');
            if(active!==serial)return;
            const results=data.reviews;
            if(number(results.total)>0&&page>Number(results.last_page))return load(Number(results.last_page));
            lastPage=Number(results.last_page);
            updateSummary(data.stats);renderCards(results.data);
            list.hidden=false;empty.hidden=results.data.length>0;
            empty.querySelector('[data-review-empty-title]').textContent=number(data.stats.total)?'Chưa có nhận xét phù hợp':'Chưa có đánh giá được duyệt';
            empty.querySelector('[data-review-empty-text]').textContent=number(data.stats.total)?'Thử chọn tất cả số sao để xem các nhận xét khác.':'Nhận xét của bạn sẽ giúp những khách hàng tiếp theo hiểu thêm về khách sạn.';
            status.textContent=number(results.total)?'Đang xem '+results.from+'–'+results.to+' / '+results.total+' nhận xét':'0 nhận xét phù hợp';
            pagination.hidden=lastPage<=1;
            document.getElementById('hotel-review-page').textContent='Trang '+page+' / '+lastPage;
        }catch(exception){
            if(active!==serial)return;
            error.hidden=false;list.hidden=true;empty.hidden=true;pagination.hidden=true;status.textContent='Chưa tải được nhận xét';
        }finally{
            clearTimeout(timeout);
            if(active===serial){section.dataset.loading='false';section.setAttribute('aria-busy','false');previous.disabled=page<=1;next.disabled=page>=lastPage;}
        }
    }

    rating.addEventListener('change',()=>load());sort.addEventListener('change',()=>load());
    section.querySelectorAll('[data-review-score]').forEach(button=>button.addEventListener('click',()=>{rating.value=rating.value===button.dataset.reviewScore?'':button.dataset.reviewScore;load();}));
    previous.addEventListener('click',()=>{if(page>1)load(page-1);});next.addEventListener('click',()=>{if(page<lastPage)load(page+1);});
    document.getElementById('hotel-review-retry').addEventListener('click',()=>load(page));
    section.addEventListener('error',event=>{if(event.target.matches?.('[data-review-avatar]'))event.target.hidden=true;},true);
    section.querySelectorAll('[data-review-avatar]').forEach(image=>{if(image.complete&&image.naturalWidth===0)image.hidden=true;});
    const total=Number(section.dataset.total)||0;
    if(total)status.textContent='Đang xem '+((page-1)*6+1)+'–'+Math.min(page*6,total)+' / '+total+' nhận xét';
})();
</script>
