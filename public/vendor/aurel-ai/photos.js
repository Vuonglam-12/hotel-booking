/* Shared, attributed place photos for the itinerary and its assistant. */
(() => {
    'use strict';
    const node = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text) n.textContent = text; return n; };
    const safeLink = (label, value) => {
        try { const url = new URL(value, location.origin); if (!['http:','https:'].includes(url.protocol)) return null;
            const a = node('a','',label); a.href = url.href; a.target = '_blank'; a.rel = 'noopener noreferrer'; return a;
        } catch (_) { return null; }
    };
    function imageUrl(value) {
        if (typeof value !== 'string' || !value.trim()) return null;
        try { const url = new URL(value, location.origin);
            return ['http:','https:'].includes(url.protocol) && (url.origin === location.origin || url.protocol === 'https:' && ['upload.wikimedia.org','thumb.wikimedia.org'].includes(url.hostname)) ? url.href : null;
        } catch (_) { return null; }
    }
    function credits(data) {
        let author = data.author || '', note = data.attribution_note || '';
        if (/https?:\/\/|EXIF|JPEG|progressive|\|/i.test(author) || author.length > 180) { note ||= author; author = ''; }
        const urls = [...new Set(note.match(/https?:\/\/[^\s|<>]+/g) || [])];
        const source = urls.find(u => u.includes('commons.wikimedia.org/wiki/')) || data.source_url;
        const license = data.license || note.match(/\b(?:CC BY(?:-SA)? [1-4]\.0|CC0(?: 1\.0)?|Public domain)\b/i)?.[0];
        const licenseUrl = data.license_url || urls.find(u => u.includes('creativecommons.org/'));
        const content = node('div','ai-photo-credit-content');
        if (author) content.append(node('p','','Tác giả: ' + author));
        if (data.captured_year) content.append(node('p','','Ảnh chụp năm ' + data.captured_year));
        for (const [label, url] of [['Nguồn ảnh',source],[license || 'Giấy phép',licenseUrl]]) {
            if (url) { const a = safeLink(label,url); if (a) content.append(a); }
        }
        if (license && !licenseUrl) content.append(node('p','',license));
        if (note) content.append(node('p','ai-photo-credit-note',note.split(/\s*\|\s*/).map(part => part.replace(/(?:Đổi kích thước|Resized to|nén JPEG|chỉnh hướng EXIF|bỏ EXIF)[^|]*/iu,'Ảnh đã được tối ưu kích thước và định dạng.')).join(' · ')));
        return content;
    }
    let viewer, opener;
    function open(data, title, trigger) {
        const url = imageUrl(data?.url); if (!url) return;
        if (!viewer) {
            viewer = node('dialog','ai-photo-viewer'); viewer.id = 'aiPhotoViewer'; viewer.setAttribute('aria-labelledby','aiPhotoViewerTitle');
            (document.getElementById('tripPlanner') || document.body).append(viewer);
            viewer.addEventListener('close', () => {
                if (viewer.open) return;
                document.documentElement.classList.remove('ai-photo-open');
                if (opener?.isConnected && (document.activeElement === document.body || viewer.contains(document.activeElement))) opener.focus({preventScroll:true});
            });
            viewer.addEventListener('click', e => { if (e.target !== viewer) return; const r = viewer.getBoundingClientRect();
                if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) viewer.close(); });
        }
        opener = trigger;
        const header = node('div','ai-photo-viewer-heading'), heading = node('h2','',title || data.alt || 'Ảnh địa điểm'); heading.id = 'aiPhotoViewerTitle';
        const close = node('button','ai-photo-close','×'); close.type = 'button'; close.setAttribute('aria-label','Đóng ảnh'); close.addEventListener('click', () => viewer.close()); header.append(heading,close);
        const image = node('img'); image.src = url; image.alt = data.alt || title || 'Ảnh địa điểm'; image.referrerPolicy = 'no-referrer'; image.decoding = 'async';
        image.addEventListener('error', () => { image.replaceWith(node('p','ai-photo-error','Chưa tải được ảnh. Bạn có thể thử lại hoặc mở nguồn ảnh bên dưới.')); }, {once:true});
        viewer.replaceChildren(header,image,credits(data));
        viewer.showModal(); document.documentElement.classList.add('ai-photo-open'); close.focus();
    }
    function render(container, data, title, options = {}) {
        if (!data?.url) return null;
        const url = imageUrl(data.url); if (!url) return null;
        const figure = node('figure','ai-place-photo'), preview = node('button','ai-place-photo-preview'), image = node('img');
        preview.type = 'button'; preview.setAttribute('aria-label','Xem ảnh ' + (title || data.alt || 'địa điểm'));
        image.src = url; image.alt = data.alt || title || 'Ảnh địa điểm'; image.loading = 'lazy'; image.decoding = 'async'; image.referrerPolicy = 'no-referrer';
        image.addEventListener('error', () => { figure.remove(); options.onError?.();
            if (container.classList.contains('ai-card-media')) { container.classList.add('ai-card-media-empty'); container.append(node('span','ai-panel-subtitle','Chưa có ảnh')); }
        }, {once:true});
        preview.append(image,node('span','ai-photo-zoom','Xem ảnh')); preview.addEventListener('click', () => open(data,title,preview)); figure.append(preview);
        const credit = credits(data);
        if (credit.childElementCount) { const caption = node('figcaption'), details = node('details','ai-photo-credits'); details.append(node('summary','','Thông tin ảnh'),credit); caption.append(details); figure.append(caption); }
        container.append(figure); return figure;
    }
    window.AurelPlacePhotos = {render,open};
})();
