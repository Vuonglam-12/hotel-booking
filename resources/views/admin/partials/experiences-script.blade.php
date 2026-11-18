<script>
(() => {
    const root = document.getElementById('admin-experiences');
    const $ = selector => root.querySelector(selector);
    const dialog = $('#exa-editor'), form = $('#exa-form');
    let locations = [], categories = [], rows = [], page = 1, lastPage = 1, currentId = null, selectedFiles = [], objectUrls = [], opener;
    let loadSerial = 0;
    const make = (tag, className, text) => { const node = document.createElement(tag); node.className = className; if (text !== undefined) node.textContent = text; return node; };
    const safeUrl = value => { if (typeof value !== 'string' || !value.trim()) return ''; try { const url = new URL(value, location.origin); return url.origin === location.origin && ['http:', 'https:'].includes(url.protocol) ? url.href : ''; } catch { return ''; } };
    async function api(endpoint, options = {}) {
        const response = await adminApi(endpoint, options);
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join('\n') : response.status === 503 ? 'Module chưa sẵn sàng hoặc kết nối bị gián đoạn. Vui lòng thử lại sau.' : data.message || 'Không thể hoàn thành thao tác.');
        return data;
    }
    function error(message) { $('#exa-form-error').textContent = message; $('#exa-form-error').hidden = !message; }
    function publicationHelp() {
        const publish = $('#exa-publish').checked;
        $('#exa-verify').required = publish;
        $('#exa-source').required = publish;
        $('#exa-category').required = publish;
        $('#exa-submit').textContent = publish ? 'Lưu và xuất bản' : 'Lưu bản nháp';
        $('#exa-submit').dataset.adminAction = publish ? 'publish' : 'save';
        $('#exa-save-help').textContent = publish
            ? 'Để hiển thị trên website: chọn đúng địa phương và danh mục, cung cấp nguồn, bật hoạt động và xác nhận đã kiểm tra. Mã địa phương phải được gán trước khi xuất bản.'
            : 'Bản nháp được lưu để tiếp tục chỉnh sửa và chưa hiển thị trên website. Chọn “Xuất bản lên trang Trải nghiệm” khi nội dung và ảnh đã được kiểm tra.';
    }
    function showCode() { const loc = locations.find(item => item.id === Number($('#exa-location').value)); $('#exa-location-code').value = loc?.code || ''; $('#exa-location-code').disabled = Boolean(loc?.code); $('#exa-save-code').disabled = Boolean(loc?.code) || !loc; }
    function clearUploads() { objectUrls.forEach(url => URL.revokeObjectURL(url)); objectUrls = []; }
    function renderNewImages() {
        clearUploads(); $('#exa-new-images').replaceChildren();
        selectedFiles.forEach((file, index) => {
            const card = make('div', 'exa-image'), image = make('img', ''); image.src = URL.createObjectURL(file); objectUrls.push(image.src); image.alt = file.name;
            const controls = make('div', 'exa-image-controls'), label = make('label', ''), radio = make('input', ''); radio.type = 'radio'; radio.name = 'cover-image'; radio.dataset.newImage = index;
            label.append(radio, document.createTextNode('Ảnh đại diện')); controls.append(label);
            const remove = make('button', 'exa-button', 'Bỏ ảnh mới'); remove.type = 'button'; remove.dataset.adminAction = 'cancel'; remove.addEventListener('click', () => { selectedFiles.splice(index, 1); renderNewImages(); });
            controls.append(remove); card.append(image, controls); $('#exa-new-images').append(card);
        });
    }
    function renderImages(images) {
        $('#exa-images').replaceChildren();
        images.forEach(item => {
            const card = make('div', 'exa-image'); const url = safeUrl(item.display_url);
            if (url) { const image = make('img', ''); image.src = url; image.alt = item.caption || 'Ảnh đã lưu'; card.append(image); }
            else card.append(make('p', 'exa-note', 'Không tìm thấy ảnh'));
            const controls = make('div', 'exa-image-controls');
            const cover = make('label', ''), radio = make('input', ''); radio.type = 'radio'; radio.name = 'cover-image'; radio.dataset.savedImage = item.id; radio.checked = item.is_primary;
            cover.append(radio, document.createTextNode('Ảnh đại diện'));
            const deletion = make('label', ''), checkbox = make('input', ''); checkbox.type = 'checkbox'; checkbox.dataset.deleteImage = item.id;
            checkbox.addEventListener('change', () => { radio.disabled = checkbox.checked; if (checkbox.checked) radio.checked = false; });
            deletion.append(checkbox, document.createTextNode('Bỏ khỏi gallery')); controls.append(cover, deletion, make('span', 'exa-note', item.source_note || 'Chưa có nguồn ảnh'));
            card.append(controls); $('#exa-images').append(card);
        });
    }
    function edit(item = null) {
        opener = document.activeElement; currentId = item?.id || null; form.reset(); error(''); selectedFiles = []; renderNewImages();
        $('#exa-editor-title').textContent = item ? 'Chỉnh sửa trải nghiệm' : 'Thêm trải nghiệm';
        const fields = ['name','location_id','item_type','slug','description','address','experience_category','experience_summary','experience_duration_minutes','experience_source_url','open_time','close_time'];
        fields.forEach(name => { const field = form.elements.namedItem(name); field.value = item?.[name] ?? (name === 'item_type' ? 'attraction' : ''); });
        [$('#exa-open'),$('#exa-close-time')].forEach(field=>field.dispatchEvent(new Event('change')));
        if (!item) $('#exa-location').value = $('#exa-location-filter').value || locations[0]?.id || '';
        $('#exa-active').checked = Boolean(item?.is_active); $('#exa-publish').checked = Boolean(item?.experience_published_at); $('#exa-verify').checked = false; publicationHelp();
        renderImages(item?.images || []); showCode(); dialog.showModal(); $('#exa-name').focus();
    }
    async function load() {
        const serial = ++loadSerial; $('#exa-loading').hidden = false;
        ['table','empty','error','pagination'].forEach(name => $('#exa-' + name).hidden = true);
        const params = new URLSearchParams({page, archived: $('#exa-archive-filter').value});
        if ($('#exa-location-filter').value) params.set('location_id', $('#exa-location-filter').value);
        if ($('#exa-query').value.trim()) params.set('q', $('#exa-query').value.trim());
        try {
            const data = await api('/attractions?' + params);
            if (serial !== loadSerial) return;
            rows = data.data; lastPage = data.meta.last_page; $('#exa-rows').replaceChildren();
            rows.forEach(item => {
                const tr = make('tr', ''), nameTd = make('td', ''), name = make('div', 'exa-name');
                const cover = item.images.find(image => image.display_url), url = safeUrl(cover?.display_url);
                if (url) { const image = make('img', 'exa-thumb'); image.src = url; image.alt = ''; name.append(image); }
                const title = make('div', ''); title.append(make('strong', '', item.name), make('small', '', item.location?.name || '')); name.append(title); nameTd.append(name);
                const category = categories.find(category => category.key === item.experience_category);
                const statusTd = make('td', ''), published = item.is_active && item.experience_published_at && item.experience_verified_at;
                const badge = make('span', 'exa-status', item.experience_archived_at ? 'Đã lưu trữ' : published ? 'Đã xuất bản' : 'Bản nháp');
                badge.dataset.adminTone = item.experience_archived_at ? 'neutral' : published ? 'success' : 'neutral'; statusTd.append(badge);
                const activeTd = make('td', ''), toggle = make('button', 'exa-button', item.is_active ? 'Đang bật' : 'Đang tắt'); toggle.type = 'button'; toggle.dataset.adminAction = item.is_active ? 'pause' : 'activate'; toggle.dataset.adminTone = item.is_active ? 'success' : 'neutral'; toggle.disabled = Boolean(item.experience_archived_at); toggle.setAttribute('aria-label', 'Bật/tắt ' + item.name);
                toggle.addEventListener('click', async () => { toggle.disabled = true; try { await api('/attractions/' + item.id + '/toggle', {method:'PUT'}); await load(); } catch (e) { adminToast(e.message,'error'); toggle.disabled = false; } }); activeTd.append(toggle);
                const actionTd = make('td',''), actions = make('div','exa-actions');
                if (!item.experience_archived_at) { const button = make('button','exa-button','Sửa'); button.type='button'; button.dataset.adminAction='edit'; button.addEventListener('click', () => edit(item)); actions.append(button); }
                const archive = make('button','exa-button', item.experience_archived_at ? 'Khôi phục' : 'Lưu trữ'); archive.type='button'; archive.dataset.adminAction=item.experience_archived_at?'restore':'archive';
                archive.addEventListener('click', async () => {
                    if (!await adminConfirm({title:(item.experience_archived_at ? 'Khôi phục' : 'Lưu trữ')+' trải nghiệm?',message:'“'+item.name+'”\nDữ liệu và ảnh được giữ lại. Khôi phục sẽ đưa về bản nháp, chưa xuất bản.',confirmText:item.experience_archived_at?'Khôi phục':'Lưu trữ',tone:item.experience_archived_at?'success':'neutral'})) return;
                    archive.disabled = true;
                    try { const result = await api('/attractions/' + item.id + (item.experience_archived_at ? '/restore' : ''), {method: item.experience_archived_at ? 'POST' : 'DELETE'}); adminToast(result.message,'success'); await load(); }
                    catch (e) { adminToast(e.message,'error'); archive.disabled = false; }
                });
                actions.append(archive); actionTd.append(actions); tr.append(nameTd,make('td','',category?.label || 'Dữ liệu cũ'),statusTd,activeTd,actionTd); $('#exa-rows').append(tr);
            });
            $('#exa-table').hidden = !rows.length; $('#exa-empty').hidden = rows.length > 0;
            $('#exa-pagination').hidden = lastPage <= 1 && page === 1; $('#exa-page-number').textContent = 'Trang ' + page + ' / ' + lastPage;
            $('#exa-prev').disabled = page <= 1; $('#exa-next').disabled = page >= lastPage;
        } catch (e) { if (serial === loadSerial) { $('#exa-error').hidden = false; $('#exa-error p').textContent = e.message; } }
        finally { if (serial === loadSerial) $('#exa-loading').hidden = true; }
    }
    $('#exa-category').addEventListener('change', () => { const category = categories.find(item => item.key === $('#exa-category').value); if (category) $('#exa-item-type').value = category.item_type; });
    $('#exa-location').addEventListener('change', showCode);
    $('#exa-publish').addEventListener('change', publicationHelp);
    $('#exa-save-code').addEventListener('click', async () => {
        const loc = locations.find(item => item.id === Number($('#exa-location').value)); if (!loc) return;
        const code = $('#exa-location-code').value.trim(); if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(code)) { error('Mã địa phương chỉ gồm chữ thường, số và dấu gạch nối.'); return; }
        $('#exa-save-code').disabled = true;
        try { const result = await api('/locations/' + loc.id, {method:'PUT',body:JSON.stringify({code})}); loc.code = result.code; showCode(); error(''); }
        catch (e) { error(e.message); $('#exa-save-code').disabled = false; }
    });
    $('#exa-files').addEventListener('change', () => {
        const files = [...$('#exa-files').files];
        if (files.some(file => file.size > 2*1024*1024 || !['image/jpeg','image/png','image/webp'].includes(file.type))) { error('Chỉ nhận JPG, PNG, WebP tối đa 2 MB/ảnh.'); $('#exa-files').value=''; return; }
        selectedFiles = files; renderNewImages(); error('');
    });
    form.addEventListener('submit', async event => {
        event.preventDefault(); error('');
        const body = new FormData();
        ['name','location_id','item_type','slug','description','address','experience_category','experience_summary','experience_duration_minutes','experience_source_url','open_time','close_time','image_source','image_caption'].forEach(name => { const value = form.elements.namedItem(name).value; if (name !== 'slug' || value) body.append(name,value); });
        ['is_active','verify','publish'].forEach(name => body.append(name, form.elements.namedItem(name).checked ? '1':'0'));
        const changes = {delete_ids:[...form.querySelectorAll('[data-delete-image]:checked')].map(input => Number(input.dataset.deleteImage))};
        const cover = form.querySelector('[name="cover-image"]:checked');
        if (cover?.dataset.savedImage) changes.primary_id = Number(cover.dataset.savedImage);
        if (cover?.dataset.newImage !== undefined) changes.primary_new = Number(cover.dataset.newImage);
        body.append('image_changes',JSON.stringify(changes)); selectedFiles.forEach(file => body.append('images[]',file));
        if (selectedFiles.length && !$('#exa-image-source').value.trim()) { error('Cần nguồn và quyền sử dụng cho ảnh mới.'); return; }
        if (currentId) body.append('_method','PUT');
        $('#exa-fields').disabled = true; $('#exa-submit').disabled = true; adminBusy($('#exa-submit'),true);
        try {
            const saved = await api(currentId ? '/attractions/' + currentId : '/locations/' + $('#exa-location').value + '/attractions', {method:'POST',body});
            dialog.close();
            adminToast(saved.is_active && saved.experience_verified_at && saved.experience_published_at
                ? 'Đã lưu và xuất bản trải nghiệm lên website.' : 'Đã lưu bản nháp. Trải nghiệm chưa hiển thị trên website.','success');
            await load();
        }
        catch (e) { error(e.message); }
        finally { $('#exa-fields').disabled = false; $('#exa-submit').disabled = false; adminBusy($('#exa-submit'),false); }
    });
    root.querySelectorAll('[data-exa-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => { clearUploads(); opener?.focus({preventScroll:true}); });
    $('#exa-create').addEventListener('click', () => edit());
    $('#exa-filters').addEventListener('submit', event => { event.preventDefault(); page=1; load(); });
    [$('#exa-location-filter'),$('#exa-archive-filter')].forEach(input => input.addEventListener('change', () => { page=1; load(); }));
    $('#exa-prev').addEventListener('click', () => { if(page>1){page--;load();} }); $('#exa-next').addEventListener('click', () => { if(page<lastPage){page++;load();} });
    async function initialize() {
        try {
            const [meta, data] = await Promise.all([api('/experiences/meta'),api('/locations')]); categories=meta.categories; locations=data;
            $('#exa-location-filter').replaceChildren(new Option('Tất cả địa phương','')); $('#exa-location').replaceChildren(); $('#exa-category').replaceChildren(new Option('Chưa phân loại',''));
            locations.forEach(item => { $('#exa-location-filter').add(new Option(item.name,item.id)); $('#exa-location').add(new Option(item.name,item.id)); });
            categories.forEach(item => $('#exa-category').add(new Option(item.label,item.key)));
            $('#exa-create').disabled = !locations.length; await load();
        } catch (e) { $('#exa-loading').hidden=true; $('#exa-error').hidden=false; $('#exa-error p').textContent=e.message; }
    }
    $('#exa-retry').addEventListener('click', initialize);
    initialize();
})();
</script>
