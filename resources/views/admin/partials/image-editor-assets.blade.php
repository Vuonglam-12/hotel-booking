@push('styles')
<style>
.admin-image-editor{min-width:0}.admin-image-editor fieldset{border:0;padding:0;margin:0;min-width:0}.image-editor-heading{font-size:13px;font-weight:700;margin:8px 0}.image-editor-hint{font-size:12px;color:#667085;line-height:1.5;margin:6px 0}.image-editor-status{font-size:12px;line-height:1.5;margin:0 0 8px;overflow-wrap:anywhere}.image-editor-status:empty{display:none}.image-editor-status[data-error=true]{color:#b42318}.image-editor-gallery-head{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:14px}.image-editor-cover{position:relative;border:1px solid #dce4ed;border-radius:14px;overflow:hidden;background:#f8fafc;width:100%;max-width:360px}.image-editor-cover img{display:block;width:100%;aspect-ratio:16/9;object-fit:cover}.image-editor-cover .image-editor-action{position:absolute;bottom:10px;right:10px;box-shadow:0 2px 6px #0002}.image-editor-cover-empty{display:grid;place-items:center;aspect-ratio:16/9;padding:14px;color:#667085;font-size:13px}.image-editor-cover-empty .image-editor-action{position:static;margin-top:8px}.image-editor-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.image-editor-card{border:1px solid #dce4ed;border-radius:11px;padding:5px;min-width:0;background:#fff}.image-editor-card.is-primary{border-color:#2563eb;box-shadow:0 0 0 1px #2563eb}.image-editor-card.is-deleted{border-color:#fda29b;background:#fff1f0}.image-editor-card.is-deleted .image-editor-thumb img{opacity:.35}.image-editor-thumb{position:relative}.image-editor-thumb img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:7px}.image-editor-badges{position:absolute;top:5px;left:5px;right:5px;display:flex;gap:3px;flex-wrap:wrap;pointer-events:none}.image-editor-badge{padding:2px 5px;border-radius:4px;font-size:10px;line-height:1.4;font-weight:600;background:#fff;color:#344054;box-shadow:0 1px 4px #0002}.image-editor-badge.is-cover{background:#2563eb;color:#fff}.image-editor-badge.is-pending{background:#e0f2fe;color:#075985}.image-editor-card-actions{display:grid;gap:4px;margin-top:5px}.image-editor-primary{display:flex;align-items:center;justify-content:center;gap:4px;font-size:11px;color:#344054;cursor:pointer;padding:4px 0}.image-editor-primary input[type=radio]{width:auto!important;margin:0;padding:0;accent-color:#2563eb}.image-editor-action{border:1px solid #dce4ed;background:#fff;color:#344054;border-radius:7px;padding:6px 8px;font:inherit;font-size:12px;cursor:pointer;line-height:1.4}.image-editor-action:disabled{opacity:.5;cursor:wait}.image-editor-card .image-editor-action{font-size:11px;padding:4px}.image-editor-add{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;min-height:125px;border:1px dashed #93c5fd;border-radius:11px;background:#f8fbff;color:#2563eb;font:inherit;font-size:12px;cursor:pointer}.image-editor-add span{font-size:24px;line-height:1}.admin-image-editor input[hidden]{display:none!important}.admin-image-editor button:focus-visible,.image-editor-primary:has(input:focus-visible){outline:2px solid #2563eb;outline-offset:2px}@media(max-width:600px){.image-editor-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.image-editor-cover{max-width:100%}}
</style>
<style>
.image-editor-upload-row{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px}.image-editor-upload{border:0;border-radius:8px;padding:9px 14px;background:#2563eb;color:#fff;font:inherit;font-size:13px;font-weight:600;cursor:pointer}.image-editor-upload:disabled{opacity:.5;cursor:wait}.image-editor-unavailable{grid-column:1/-1;padding:16px;border:1px dashed #cbd5e1;border-radius:10px;color:#667085;font-size:12px;line-height:1.6}.image-editor-cover-empty.is-loading{background:linear-gradient(110deg,#f8fafc,#e2e8f0,#f8fafc)}
</style>
@endpush
@push('scripts')
<script>
window.AdminImageEditor = class {
    constructor(id, onChange = () => {}) {
        this.root = document.getElementById(id); this.onChange = onChange;
        this.placeholder = @js(\App\Support\AccommodationImage::placeholder());
        this.root.addEventListener('error', event => {
            const image=event.target;
            if(image.tagName==='IMG' && image.src!==this.placeholder) image.src=this.placeholder;
        }, true);
        this.limits = @js(\App\Services\AdminAccommodationImageService::limits());
        this.version = 0; this.reset();
        this.root.querySelector('[data-image-files]').addEventListener('change', event => {
            this.addFiles([...event.target.files]); event.target.value = '';
        });
        this.root.querySelector('[data-image-cover-file]').addEventListener('change', event => {
            this.addFiles([...event.target.files], {asCover:true}); event.target.value = '';
        });
        this.root.addEventListener('click', event => {
            if (event.target.closest('[data-image-pick]')) {
                if (this.ready && !this.saving) {
                    const cover = event.target.closest('[data-image-pick]').dataset.imagePick === 'cover';
                    this.root.querySelector(cover?'[data-image-cover-file]':'[data-image-files]').click();
                }
                return;
            }
            const button = event.target.closest('[data-image-action]');
            if (!button || this.saving || !this.ready) return;
            const key = button.dataset.imageKey;
            if (['move-up','move-down'].includes(button.dataset.imageAction)) {
                const visible = this.order.filter(item => !this.deleted.has(Number(item.slice(2))) || item.startsWith('n-'));
                const index = visible.indexOf(key), next = index + (button.dataset.imageAction === 'move-up' ? -1 : 1);
                if (next < 0 || next >= visible.length) return;
                const a = this.order.indexOf(key), b = this.order.indexOf(visible[next]);
                [this.order[a],this.order[b]] = [this.order[b],this.order[a]];
            } else if (button.dataset.imageAction === 'remove-new') {
                const item = this.fresh.find(item => item.key === key);
                URL.revokeObjectURL(item.url); this.fresh = this.fresh.filter(candidate => candidate !== item);
                this.order = this.order.filter(candidate => candidate !== key);
                if (this.primary === key) this.primary = this.previousPrimary();
            } else {
                const id = Number(key.slice(2));
                this.deleted.has(id) ? this.deleted.delete(id) : this.deleted.add(id);
            }
            this.render(); this.status('Có thay đổi ảnh chưa lưu.'); this.onChange();
        });
        this.root.addEventListener('change', event => {
            if (event.target.matches('[data-image-primary]')) { const key=event.target.value; this.selectPrimary(key); this.render(); this.root.querySelector(`[data-image-primary][value="${key}"]`)?.focus(); this.status('Có thay đổi ảnh chưa lưu.'); this.onChange(); }
        });
        this.root.querySelector('[data-image-retry]').addEventListener('click', () => this.open(this.endpoint));
    }
    reset() {
        this.version++; this.controller?.abort();
        (this.fresh || []).forEach(item => URL.revokeObjectURL(item.url));
        this.saved = []; this.fresh = []; this.deleted = new Set(); this.primary = null; this.originalPrimary = null;
        this.primaryHistory = [];
        this.order = []; this.ordering = false;
        this.endpoint = null; this.ready = false; this.saving = false; this.sequence = 0;
        this.loadState = 'idle';
        this.root.setAttribute('aria-busy', 'false');
        this.root.querySelector('[data-image-files]').value = ''; this.root.querySelector('[data-image-cover-file]').value = ''; this.status(''); this.render(); this.lock();
    }
    async open(endpoint, {ordering=false} = {}) {
        this.reset(); this.endpoint = endpoint;
        this.ordering = ordering;
        if (!endpoint) { this.ready = true; this.loadState='ready'; this.render(); this.lock(); this.onChange(); return; }
        const version = this.version; this.controller = new AbortController();
        this.loadState='loading'; this.render();
        this.status('Đang tải ảnh…'); this.root.setAttribute('aria-busy', 'true'); this.onChange();
        try {
            const response = await adminApi(endpoint, {signal: this.controller.signal});
            const data = await response.json();
            if (version !== this.version) return;
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Không tải được ảnh.');
            this.saved = data.images.map(item => ({...item, id: Number(item.id), key: 's-' + item.id}));
            this.order = this.saved.map(item => item.key); this.ordering = data.image_ordering === true || ordering;
            this.originalPrimary = this.saved.find(item => Boolean(Number(item.is_primary)))?.key || this.saved[0]?.key || null;
            this.primary = this.saved.find(item=>item.key===this.originalPrimary && item.display_url!==this.placeholder)?.key
                || this.saved.find(item=>item.display_url!==this.placeholder)?.key || this.originalPrimary;
            this.limits = data.image_validation || this.limits; this.ready = true; this.loadState='ready';
            this.root.querySelector('[data-image-limits]').textContent = this.limits.label;
            this.status(this.saved.length ? 'Đã tải ' + this.saved.length + ' ảnh đã lưu.' : 'Chưa có ảnh. Chọn ảnh mới để bổ sung.'); this.render();
        } catch (error) {
            if (version !== this.version || error.name === 'AbortError') return;
            this.loadState='error'; this.render();
            this.status(error.message || 'Không tải được ảnh. Vui lòng thử lại.', true);
            this.root.querySelector('[data-image-retry]').hidden = false;
        } finally {
            if (version === this.version) { this.root.setAttribute('aria-busy', 'false'); this.lock(); this.onChange(); }
        }
    }
    addFiles(files, {asCover=false} = {}) {
        if (!this.ready || this.saving) return;
        const errors = [];
        const firstNew = this.fresh.length;
        for (const file of asCover?files.slice(0,1):files) {
            if (!this.limits.mime_types.includes(file.type) || file.size === 0) { errors.push(file.name + ': chỉ hỗ trợ JPG, PNG, WebP hợp lệ.'); continue; }
            if (file.size > this.limits.max_bytes) { errors.push(file.name + ': vượt dung lượng 2 MB.'); continue; }
            if (this.fresh.length >= this.limits.max_files) { errors.push('Tối đa ' + this.limits.max_files + ' ảnh mới/lần lưu.'); break; }
            this.fresh.push({key: 'n-' + (++this.sequence), file, url: URL.createObjectURL(file)});
            this.order.push(this.fresh[this.fresh.length-1].key);
        }
        if (asCover && this.fresh[firstNew]) this.selectPrimary(this.fresh[firstNew].key);
        if (!this.primary) this.primary = this.fallbackPrimary();
        this.render(); this.status(errors.join(' ') || 'Đã chọn ' + this.fresh.length + ' ảnh mới · chưa lưu.', errors.length > 0); this.onChange();
    }
    status(message, error = false) {
        const node = this.root.querySelector('[data-image-status]'); node.textContent = message; node.dataset.error = String(error);
        this.root.querySelector('[data-image-retry]').hidden = true;
    }
    lock() { this.root.querySelector('[data-image-controls]').disabled = !this.ready || this.saving; }
    setSaving(saving) { this.saving = saving; this.lock(); if (saving) this.status('Đang lưu ảnh cùng thông tin…'); this.onChange(); }
    fallbackPrimary() {
        const items=new Map([...this.saved,...this.fresh].map(item=>[item.key,item]));
        const available=this.order.map(key=>items.get(key)).filter(item=>item && (item.key.startsWith('n-')||!this.deleted.has(item.id)));
        return available.find(item=>item.key.startsWith('n-')||item.display_url!==this.placeholder)?.key || available[0]?.key || null;
    }
    selectPrimary(key) { if (key !== this.primary && this.primary) this.primaryHistory.push(this.primary); this.primary=key; }
    previousPrimary() {
        const available=key=>[...this.saved.filter(item=>!this.deleted.has(item.id)),...this.fresh].some(item=>item.key===key);
        while(this.primaryHistory.length){const key=this.primaryHistory.pop();if(available(key))return key;}
        return available(this.originalPrimary)?this.originalPrimary:this.fallbackPrimary();
    }
    render() {
        const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
        if (!this.ready) {
            const message=this.loadState==='loading'?'Đang tải ảnh…':this.loadState==='error'?'Không tải được ảnh. Bấm Tải lại ảnh để thử lại.':'Chờ tải dữ liệu ảnh của form đang chọn.';
            this.root.querySelector('[data-image-cover]').innerHTML=`<div class="image-editor-cover-empty ${this.loadState==='loading'?'is-loading':''}">${message}</div>`;
            this.root.querySelector('[data-image-gallery]').innerHTML=`<div class="image-editor-unavailable">${message}</div>`;
            this.root.querySelector('[data-image-count]').textContent='';
            return;
        }
        const effectivePrimary = this.deleted.has(Number(this.primary?.slice(2))) && this.primary?.startsWith('s-') ? this.fallbackPrimary() : this.primary;
        const card = (item, fresh, index) => {
            const deleted = !fresh && this.deleted.has(item.id);
            const name = fresh ? item.file.name : (item.caption || 'Ảnh ' + (index+1));
            const primary = effectivePrimary === item.key && !deleted;
            return `<article class="image-editor-card ${fresh ? 'is-new' : 'is-saved'} ${deleted ? 'is-deleted' : ''} ${primary?'is-primary':''}" data-image-key="${item.key}">
                <div class="image-editor-thumb"><img src="${esc(fresh ? item.url : item.display_url)}" alt="${esc(name)}">
                <div class="image-editor-badges">${primary?'<span class="image-editor-badge is-cover">Đại diện</span>':''}${fresh?'<span class="image-editor-badge is-pending">Chưa lưu</span>':''}${deleted?'<span class="image-editor-badge">Sẽ xóa</span>':''}</div></div>
                <div class="image-editor-card-actions"><label class="image-editor-primary"><input type="radio" name="${this.root.id}-primary" data-image-primary value="${item.key}" ${primary?'checked':''} ${deleted?'disabled':''} aria-label="Chọn ${esc(name)} làm đại diện">${primary?'Đại diện':'Chọn đại diện'}</label>
                <button type="button" class="image-editor-action" data-image-action="${fresh ? 'remove-new' : 'delete'}" data-admin-action="${deleted ? 'restore' : fresh ? 'cancel' : 'delete'}" data-image-key="${item.key}" aria-label="${deleted?'Hoàn tác xóa':'Xóa'} ${esc(name)}">${deleted?'Hoàn tác':'Xóa'}</button></div></article>`;
        };
        const items = new Map([...this.saved,...this.fresh].map(item => [item.key,item]));
        this.root.querySelector('[data-image-gallery]').innerHTML = this.order.map((key,index) => {
            const item = items.get(key); if (!item) return '';
            const html = card(item,key.startsWith('n-'),index);
            const controls = this.ordering && (key.startsWith('n-') || !this.deleted.has(item.id))
                ? `<button type="button" class="image-editor-action" data-image-action="move-up" data-image-key="${key}" aria-label="Đưa ảnh lên trước">↑ Trước</button><button type="button" class="image-editor-action" data-image-action="move-down" data-image-key="${key}" aria-label="Đưa ảnh ra sau">↓ Sau</button>` : '';
            return html.replace('</div></article>',controls+'</div></article>');
        }).join('') + '<button type="button" class="image-editor-add" data-image-pick="add"><span aria-hidden="true">+</span>Thêm ảnh</button>';
        const cover = [...this.saved,...this.fresh].find(item=>item.key===effectivePrimary);
        this.root.querySelector('[data-image-cover]').innerHTML = cover ? `<img src="${esc(cover.url||cover.display_url)}" alt="Ảnh đại diện đang chọn"><button type="button" class="image-editor-action" data-image-pick="cover">Đổi ảnh đại diện</button>` : '<div class="image-editor-cover-empty"><div>Chưa có ảnh đại diện<br><button type="button" class="image-editor-action" data-image-pick="cover">Chọn ảnh đại diện</button></div></div>';
        this.root.querySelector('[data-image-count]').textContent = `${this.saved.length+this.fresh.length-this.deleted.size} ảnh${this.fresh.length?' · '+this.fresh.length+' chưa lưu':''}`;
    }
    body(payload, method) {
        if (!this.ready || this.saving) throw new Error('Vui lòng chờ tải ảnh hoặc tải lại trước khi lưu.');
        const changes = {delete_ids: [...this.deleted]};
        if (this.ordering) changes.order = this.order.filter(key => key.startsWith('n-') || !this.deleted.has(Number(key.slice(2))))
            .map(key => key.startsWith('s-') ? key : 'n-' + this.fresh.findIndex(item => item.key === key));
        const primary=this.primary?.startsWith('s-')&&this.deleted.has(Number(this.primary.slice(2)))?this.fallbackPrimary():this.primary;
        if (primary?.startsWith('n-')) changes.primary_new = this.fresh.findIndex(item => item.key === primary);
        else if (primary && primary !== this.originalPrimary) changes.primary_id = Number(primary.slice(2));
        if (!this.fresh.length) return JSON.stringify({...payload, image_changes: changes});
        const body = new FormData();
        for (const [key, value] of Object.entries(payload)) {
            if (Array.isArray(value)) {
                // Normalize the empty multipart amenities array on the server.
                if (!value.length) body.append(key, '[]');
                else value.forEach((item, index) => body.append(key + '[' + index + ']', item));
            } else body.append(key, value ?? '');
        }
        body.append('image_changes', JSON.stringify(changes)); this.fresh.forEach(item => body.append('images[]', item.file));
        if (method !== 'POST') body.append('_method', method);
        return body;
    }
};
</script>
@endpush
