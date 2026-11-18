<script>
(() => {
    const root=document.getElementById('guide-page'), $=selector=>root.querySelector(selector);
    const make=(tag,cls,text)=>{const node=document.createElement(tag);node.className=cls;if(text!==undefined)node.textContent=text;return node;};
    const safeUrl=value=>{if(typeof value!=='string'||!value.trim())return '';try{const url=new URL(value,location.origin);return url.origin===location.origin&&['http:','https:'].includes(url.protocol)?url.href:'';}catch{return '';}};
    let currentPage=1,lastPage=1,category='',serial=0,controller;
    function readUrl(){const params=new URLSearchParams(location.search);$('#guide-query').value=(params.get('q')||'').slice(0,120);category=params.get('category')||'';currentPage=Math.max(1,parseInt(params.get('page'),10)||1);}
    function media(item,eager=false){const link=make('a','gd-media');link.href=safeUrl(item.url);link.setAttribute('aria-label',item.title);const url=safeUrl(item.cover_url);if(url){const image=make('img','');image.src=url;image.alt=item.title;image.loading=eager?'eager':'lazy';image.decoding='async';if(eager)image.fetchPriority='high';link.append(image);}else link.append(make('span','','Ảnh bài viết đang được cập nhật'));return link;}
    function meta(item){const box=make('div','gd-meta'),date=make('time','',item.published_label);date.dateTime=item.published_at;box.append(make('span','gd-category',item.category.name),date);return box;}
    function card(item,featured=false){const article=make('article',featured?'gd-featured':'gd-card');article.append(media(item,featured));const body=featured?make('div','gd-featured-content'):article;body.append(meta(item));if(featured)body.prepend(make('span','gd-eyebrow','Bài viết nổi bật'));const heading=make(featured?'h2':'h3',''),title=make('a','',item.title);title.href=safeUrl(item.url);heading.append(title);body.append(heading);if(item.excerpt)body.append(make('p','',item.excerpt));const read=make('a',featured?'gd-button gd-button--quiet':'gd-read','Đọc bài viết ↗');read.href=safeUrl(item.url);body.append(read);if(featured)article.append(body);return article;}
    function categories(items){const nav=$('#guide-categories');nav.replaceChildren();[{name:'Tất cả bài viết',slug:''},...items].forEach(item=>{const button=make('button','gd-pill',item.name);button.type='button';button.dataset.category=item.slug;button.setAttribute('aria-pressed',String(category===item.slug));button.addEventListener('click',()=>{category=item.slug;currentPage=1;load(true);});nav.append(button);});}
    function state(name){['loading','featured','heading','grid','empty','unavailable','error','pagination'].forEach(key=>$('#guide-'+key).hidden=key!==name);$('#guide-results').setAttribute('aria-busy',String(name==='loading'));}
    async function load(update=false){
        const request=++serial;controller?.abort();controller=new AbortController();state('loading');
        const params=new URLSearchParams();const query=$('#guide-query').value.trim();if(query)params.set('q',query);if(category)params.set('category',category);if(currentPage>1)params.set('page',currentPage);
        if(update)history.pushState(null,'',location.pathname+(params.size?'?'+params:''));
        try{const response=await fetch(@json(url('/api/guides'))+'?'+params,{headers:{Accept:'application/json'},signal:controller.signal});const data=await response.json();if(request!==serial)return;
            if(!response.ok){state(response.status===503&&data.code==='GUIDES_NOT_READY'?'unavailable':'error');return;}
            if(!Array.isArray(data.data)||!Array.isArray(data.categories)||!data.meta||!Number.isInteger(data.meta.total))throw new Error('Invalid guide response');
            categories(data.categories);lastPage=data.meta.last_page;currentPage=data.meta.current_page;state('grid');$('#guide-results').setAttribute('aria-busy','false');
            const featured=$('#guide-featured');featured.replaceChildren();featured.hidden=!data.featured;if(data.featured)featured.append(card(data.featured,true));
            const items=data.data.filter(item=>item.id!==data.featured?.id);$('#guide-grid').replaceChildren(...items.map(item=>card(item)));$('#guide-grid').hidden=!items.length;$('#guide-heading').hidden=!items.length;$('#guide-count').textContent=data.meta.total+' bài viết';
            $('#guide-empty').hidden=data.meta.total!==0;$('#guide-pagination').hidden=lastPage<=1;$('#guide-prev').disabled=currentPage<=1;$('#guide-next').disabled=currentPage>=lastPage;$('#guide-page-label').textContent=currentPage+' / '+lastPage;
        }catch(error){if(error.name!=='AbortError'&&request===serial)state('error');}
    }
    $('#guide-search').addEventListener('submit',event=>{event.preventDefault();currentPage=1;load(true);});
    $('#guide-retry').addEventListener('click',()=>load());$('#guide-reset').addEventListener('click',()=>{category='';currentPage=1;$('#guide-query').value='';load(true);});
    $('#guide-prev').addEventListener('click',()=>{if(currentPage>1){currentPage--;load(true);}});$('#guide-next').addEventListener('click',()=>{if(currentPage<lastPage){currentPage++;load(true);}});
    window.addEventListener('popstate',()=>{readUrl();load();});readUrl();load();
})();
</script>
