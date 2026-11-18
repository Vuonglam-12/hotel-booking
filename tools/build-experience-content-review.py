"""Build an offline approval preview from already captured catalog/source evidence.

No Laravel bootstrap, database, network, uploads or execution of ZIP contents.
"""
import base64
import csv
import hashlib
import html
import json
from pathlib import Path
import sys
import unicodedata
import zipfile

ROOT = Path(__file__).resolve().parents[1]
WORK = Path(sys.argv[1]).resolve()
ALLOWED = Path(r'C:\Users\ASUS\AppData\Local\AUREL\content-review').resolve()
if WORK.parent != ALLOWED or not WORK.is_dir():
    raise SystemExit('An existing private content-review directory is required.')
OUT = WORK / 'review'
OUT.mkdir(exist_ok=True)


def load(relative):
    return json.loads((WORK / relative).read_text(encoding='utf-8-sig'))


def save_json(path, value):
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


def e(value):
    return html.escape(str(value or ''), quote=True)


def fold(value):
    return ''.join(c for c in unicodedata.normalize('NFD', value.lower().replace('đ', 'd'))
                   if unicodedata.category(c) != 'Mn')


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


drafts = load('original/experiences_21_draft.json')
live = load('evidence/live-catalog.json')
rights = {int(r['attraction_id']): r for r in load('evidence/photo-rights-review.json')}
images = {int(r['attraction_id']): r for r in load('evidence/image-optimization.json')}
editorial_file = ROOT / 'docs/validation/experience-content-editorial-review.json'
editorial = json.loads(editorial_file.read_text(encoding='utf-8'))
edits = {int(r['id']): r for r in editorial['records']}
baseline = load('evidence/round2-baseline.json')
for old in baseline['images']:
    old_file = Path(old['file'])
    assert sha(old_file) == old['sha256'] and old_file.stat().st_mtime_ns == old['mtime_ns']
category_source = (ROOT / 'app/Support/ExperienceCategory.php').read_text(encoding='utf-8')
assert all("'" + key + "'" in category_source for key in editorial['supported_categories'])
with (ROOT / 'docs/validation/experience-mapping-21-plan.csv').open(encoding='utf-8-sig', newline='') as f:
    plan = {int(r['attraction_id']): r for r in csv.DictReader(f)}
with (WORK / 'original/experiences_21_review.csv').open(encoding='utf-8-sig', newline='') as f:
    csv_drafts = list(csv.DictReader(f))
locations = {int(r['id']): r for r in live['locations']}
attractions = {int(r['id']): r for r in live['attractions']}
assert live['status'] == 'PASS_READONLY_CATALOG_SNAPSHOT'
assert live['identity']['uuid'] == '7880df8a-92cd-11f0-85c1-a841f442ddbd'
assert len(drafts) == len(csv_drafts) == len(plan) == 21
assert len({r['existing_attraction_id'] for r in drafts}) == 21
assert {r['existing_attraction_id'] for r in drafts} == set(plan)
assert set(edits) == set(plan) and len(editorial['records']) == 21
assert len(attractions) == 47 and len(locations) == 8

labels = {'tham-quan': 'Tham quan', 'am-thuc': 'Ẩm thực', 'dia-phuong': 'Hoạt động địa phương'}
type_category = {'attraction': 'tham-quan', 'restaurant': 'am-thuc', 'activity': 'dia-phuong'}
csv_by_id = {int(r['existing_attraction_id']): r for r in csv_drafts}
assert len(csv_by_id) == 21
rows, credits = [], []
for draft in drafts:
    aid = int(draft['existing_attraction_id'])
    current, expected, tabular = attractions[aid], plan[aid], csv_by_id[aid]
    issues = []
    for field, value in draft.items():
        if str(value if value is not None else '') != tabular[field]:
            issues.append('JSON/CSV khác nhau: ' + field)
    if draft['title'] != current['name'] or draft['title'] != expected['name']:
        issues.append('Tên không khớp database/kế hoạch')
    if int(draft['location_id_proposed']) != int(expected['target_location_id']):
        issues.append('Địa phương đề xuất khác kế hoạch đối chiếu')
    if current['item_type'] != expected['item_type']:
        issues.append('Loại bản ghi khác kế hoạch')
    if int(current['location_id']) != int(expected['expected_location_id']):
        issues.append('Địa phương hiện tại đã thay đổi so với kế hoạch')
    assert int(draft['location_id_proposed']) in locations
    assert draft['publish_status'] == 'draft' and draft['review_status'] == 'needs_owner_approval'
    edit = edits[aid]
    category = edit['experience_category']
    assert category == type_category[current['item_type']] and category in editorial['supported_categories']
    assert edit['content_status'] in ('supported', 'partial', 'insufficient')
    assert len(edit['summary']) <= 500
    assert all(s['url'].startswith('https://') for s in edit['sources'])
    photo, image = rights.get(aid), images.get(aid)
    notes = list(edit['issues'])
    notes.append('Giá, tọa độ, thời lượng và giờ hoạt động chưa được chốt trong bộ duyệt; không dùng giá trị trống để ghi đè dữ liệu cũ.')
    if edit.get('photo_note'):
        notes.append(edit['photo_note'])
    credit = None
    if image:
        assert photo['status'] == 'downloaded_license_checked' and not photo['source_review_warning']
        assert sha(WORK / 'images/optimized' / f'{aid:02d}.jpg') == image['sha256']
        title = photo['canonical_title'].removeprefix('File:')
        author = photo.get('attribution') or photo['author']
        license_url = photo['license_url']
        if aid == 5:
            author = 'White House Photo Office (Obama Administration), NARA'
            license_url = 'https://creativecommons.org/publicdomain/mark/1.0/'
        changes = 'Đổi kích thước/nén JPEG, bỏ EXIF; không cắt ảnh.'
        credit_url = photo['source_page']
        note = f"{title} — {author}. {credit_url} | {photo['license']} {license_url} | {changes}"
        if len(note) > 500 and isinstance(photo['page_id'], int):
            credit_url = 'https://commons.wikimedia.org/?curid=' + str(photo['page_id'])
            note = f"{title} — {author}. {credit_url} | {photo['license']} {license_url} | {changes}"
        assert len(note) <= 500 and len(draft['title']) <= 200
        credit = {'attraction_id': aid, 'photo_title': title, 'author': photo['author'],
                  'attribution': author, 'source_page': photo['source_page'], 'license': photo['license'],
                  'license_url': license_url, 'changes': changes, 'image_source_note': note,
                  'image_caption': draft['title'], 'photo_date': photo['photo_date'],
                  'source_revision': photo['revision'], 'reviewed_at_utc': photo['reviewed_at_utc'],
                  'sha256': image['sha256'], 'share_alike': 'BY-SA' in photo['license']}
        credits.append(credit)
        credit_text = note + '\n' + 'Bản ảnh này giữ giấy phép nguồn; không chuyển quyền tác giả sang AUREL.\n'
        credit_file = WORK / 'images/optimized' / f'{aid:02d}.license.txt'
        if not credit_file.exists() or credit_file.read_text(encoding='utf-8') != credit_text:
            credit_file.write_text(credit_text, encoding='utf-8')
        photo_state = 'prepared'
    elif photo and photo['source_review_warning']:
        photo_state = 'blocked'
        notes.append('Không dùng ảnh đã đề xuất: trang Commons cảnh báo thông tin nguồn chưa được kiểm tra.')
    else:
        photo_state = 'missing'
        notes.append('Chưa chuẩn bị được ảnh đúng địa điểm với giấy phép sử dụng phù hợp; tiếp tục giữ bản nháp.')
    rows.append({'id': aid, 'title': draft['title'], 'name_match': not any('Tên' in x for x in issues),
                 'package_issues': issues, 'item_type': current['item_type'],
                 'current_location_id': current['location_id'], 'current_location': locations[int(current['location_id'])]['name'],
                 'target_location_id': draft['location_id_proposed'], 'target_location': locations[draft['location_id_proposed']]['name'],
                 'target_location_key': expected['target_location_key'],
                 'mapping_pending': int(current['location_id']) != int(draft['location_id_proposed']),
                 'raw_category': draft['category_proposed'], 'proposed_existing_category': category,
                 'category_label': labels[category], 'category_normalized': True, 'category_needs_approval': False,
                 'short_description': edit['summary'], 'visitor_note': edit['visitor_note'],
                 'original_short_description': draft['short_description'], 'original_visitor_note': draft['visitor_note'],
                 'content_status': edit['content_status'], 'sources': edit['sources'], 'editorial_changes': edit['changes'],
                 'reference_address': edit['reference_address'],
                 'ready_for_owner_review': edit['content_status'] == 'supported' and photo_state == 'prepared',
                 'current_description': current.get('description'), 'current_address': current.get('address'),
                 'photo_state': photo_state, 'image': image, 'credit': credit,
                 'rejected_source': photo['source_page'] if photo_state == 'blocked' else None,
                 'status': 'draft', 'operational_source_url': edit['sources'][0]['url'] if edit['content_status'] == 'supported' else None, 'notes': notes})

summary = {'draft_count': len(rows), 'package_mismatch_count': sum(bool(r['package_issues']) for r in rows),
           'pending_live_location_mappings': sum(r['mapping_pending'] for r in rows),
           'categories_requiring_translation': 0, 'normalized_categories': len(rows),
           'original_photos_preserved': len(baseline['images']), 'new_photos': len(images) - len(baseline['images']),
           'prepared_photos': len(images), 'blocked_photo_sources': sum(r['photo_state'] == 'blocked' for r in rows),
           'without_usable_photo_source': sum(r['photo_state'] == 'missing' for r in rows),
           'without_prepared_photo': sum(r['photo_state'] != 'prepared' for r in rows),
           'optimized_bytes': sum(r['bytes'] for r in images.values()),
           'drafts_without_operational_source': sum(not r['operational_source_url'] for r in rows),
           'content_supported': sum(r['content_status'] == 'supported' for r in rows),
           'content_partial': sum(r['content_status'] == 'partial' for r in rows),
           'content_insufficient': sum(r['content_status'] == 'insufficient' for r in rows),
           'ready_for_owner_review': sum(r['ready_for_owner_review'] for r in rows),
           'needs_additional_material_ids': [r['id'] for r in rows if not r['ready_for_owner_review']],
           'unapproved_by_owner': 21, 'database_writes': 0, 'system_uploads': 0,
           'live_snapshot_at': live['identity']['observed_at'], 'zip_sha256': 'f0e79f31dad6e88798de9f431a1eadca2ce7534a8414145eddca9ef3279d1d5a'}
save_json(OUT / 'review-data.json', {'summary': summary, 'records': rows, 'approval': 'PENDING_OWNER_REVIEW'})
save_json(OUT / 'photo-credits.json', credits)
save_json(OUT / 'photo-source-exclusions.json', {'not_downloaded_or_used': [
    {'attraction_id': 20, 'url': 'https://commons.wikimedia.org/wiki/File:The_Valley_of_Love_in_Dalat,_Vietnam.jpg',
     'reason': 'Unreviewed Wikivoyage transfer warning. Replaced by a different, reviewed source; original remains excluded.'},
    {'attraction_id': 14, 'url': 'https://commons.wikimedia.org/wiki/File:Beef_Vietnamese_Noodle_Soup_in_the_Pho_Le.jpg',
     'reason': 'Source identifies Pho Le in Yuen Long, Hong Kong; does not match Ho Chi Minh City.'},
    {'attraction_id': 23, 'url': 'https://www.flickr.com/photos/120994144@N04/29898174333/',
     'reason': 'Original photographer Nam Nguyen: All rights reserved. Blog embedding is not a reuse license.'}
]})
save_json(OUT / 'normalized-drafts.json', {'approval': 'PENDING_OWNER_REVIEW', 'not_an_import_command': True,
    'records': [{'existing_attraction_id': r['id'], 'title': r['title'], 'location_id_proposed': r['target_location_id'],
                 'location_code_proposed': r['target_location_key'], 'item_type': r['item_type'],
                 'experience_category': r['proposed_existing_category'], 'experience_summary': r['short_description'],
                 'description': r['short_description'] + '\n\n' + r['visitor_note'],
                 'reference_address_not_approved': r['reference_address'], 'reference_sources': r['sources'],
                 'image_file': f"images/optimized/{r['id']:02d}.jpg" if r['credit'] else None,
                 'image_source': r['credit']['image_source_note'] if r['credit'] else None,
                 'publish_status': 'draft', 'review_status': 'needs_owner_approval',
                 'content_status': r['content_status'], 'needs_additional_material': not r['ready_for_owner_review']}
                for r in rows]})
with (OUT / 'reconciliation.csv').open('w', encoding='utf-8-sig', newline='') as f:
    columns = ['id', 'title', 'item_type', 'current_location_id', 'current_location', 'target_location_id',
               'target_location', 'target_location_key', 'raw_category', 'proposed_existing_category', 'photo_state',
               'content_status', 'ready_for_owner_review', 'reference_address', 'status', 'package_issues']
    writer = csv.DictWriter(f, fieldnames=columns, extrasaction='ignore')
    writer.writeheader()
    writer.writerows({**r, 'package_issues': '; '.join(r['package_issues'])} for r in rows)
with (OUT / 'publication-gaps.csv').open('w', encoding='utf-8-sig', newline='') as f:
    writer = csv.DictWriter(f, fieldnames=['id', 'title', 'target_location', 'photo_state', 'content_status', 'reason'])
    writer.writeheader()
    writer.writerows({'id': r['id'], 'title': r['title'], 'target_location': r['target_location'],
                     'photo_state': r['photo_state'], 'content_status': r['content_status'],
                     'reason': '; '.join(edits[r['id']]['issues'])} for r in rows if not r['ready_for_owner_review'])

credit_md = ['# Ghi công ảnh — chỉ dùng cho bản duyệt', '',
             'Nguồn/giấy phép được đối chiếu trên Commons ngày 25/10/2026. Chưa có phê duyệt công khai của chủ dự án.',
             'Các bản nén giữ giấy phép tương ứng. CC BY-SA yêu cầu chia sẻ bản ảnh thích nghi theo giấy phép tương ứng, ghi công và nêu thay đổi.', '']
for c in credits:
    revision = c['source_revision'].get('revid') or c['source_revision'].get('sha256')
    credit_md += [f"## ID {c['attraction_id']} — {c['photo_title']}", '', c['image_source_note'], '',
                  f"Ngày ảnh: {c['photo_date']}; revision/hash trang nguồn: {revision}; SHA-256 bản nén: {c['sha256']}", '']
credit_md += ['## Không sử dụng', '', 'Ảnh ID 20 ở gói ban đầu — https://commons.wikimedia.org/wiki/File:The_Valley_of_Love_in_Dalat,_Vietnam.jpg',
              'Trang nguồn yêu cầu kiểm tra lại thông tin ảnh chuyển từ Wikivoyage. Không tải hoặc dùng ảnh này.', '',
              'Ảnh Bánh căn Lệ của Nam Nguyen — https://www.flickr.com/photos/120994144@N04/29898174333/ — All rights reserved, không tải hoặc dùng.', '',
              'Quyền tác giả/quyền sử dụng 21 đoạn văn do chủ dự án xác nhận trước khi công khai.']
(OUT / 'ATTRIBUTIONS.md').write_text('\n'.join(credit_md) + '\n', encoding='utf-8')
content_md = ['# Nội dung đã rà soát — chờ chủ dự án duyệt', '',
              'Tất cả 21 bản ghi giữ draft. Nhóm đủ tài liệu để duyệt không có nghĩa đã được phép xuất bản.', '']
for r in rows:
    content_md += [f"## ID {r['id']} — {r['title']}", '', f"Địa phương: {r['target_location']}; danh mục: {r['proposed_existing_category']}; kiểm chứng: {r['content_status']}", '',
                   r['short_description'], '', r['visitor_note'], '', 'Điều chỉnh: ' + '; '.join(r['editorial_changes']), '']
    content_md += [f"- [{s['title']}]({s['url']}): {s['supports']}" for s in r['sources']]
    content_md += [''] + ['- ' + n for n in r['notes']] + ['']
(OUT / 'CONTENT-REVIEW.md').write_text('\n'.join(content_md), encoding='utf-8')

cards = []
photo_labels = {'prepared': 'Ảnh đã chuẩn bị', 'missing': 'Chưa có ảnh đủ điều kiện', 'blocked': 'Tạm giữ ảnh nguồn'}
for r in rows:
    aid, c = r['id'], r['credit']
    if c:
        cover = (f'<button class="review-photo" data-photo="{aid}" aria-label="Xem toàn ảnh: {e(r["title"])}">'
                 f'<img src="../images/optimized/{aid:02d}.jpg" alt="{e(r["title"])}" width="{r["image"]["width"]}" height="{r["image"]["height"]}"></button>')
        credit_html = (f'<p class="review-credit">Ảnh: {e(c["attribution"])} · '
                       f'<a href="{e(c["source_page"])}" target="_blank" rel="noopener noreferrer">Trang nguồn</a> · '
                       f'<a href="{e(c["license_url"])}" target="_blank" rel="noopener noreferrer">{e(c["license"])}</a>'
                       f'<br>{e(c["changes"])}</p>')
        source_detail = f'<p class="review-credit">{e(c["photo_title"])} · Ngày ảnh: {e(c["photo_date"])}<br>Ghi công khi dùng ảnh:</p><p class="credit-text">{e(c["image_source_note"])}</p>'
    else:
        cover = f'<div class="exp-no-image"><span>{photo_labels[r["photo_state"]]}</span><span>Không dùng ảnh thay thế không rõ nguồn</span></div>'
        credit_html = ''
        source_detail = (f'<p><a href="{e(r["rejected_source"])}" target="_blank" rel="noopener noreferrer">Xem nguồn đang bị tạm giữ</a> — chưa dùng trong bộ duyệt này.</p>' if r['rejected_source'] else '<p>Chưa tìm được ảnh đúng địa điểm có giấy phép phù hợp. Không dùng ảnh thay thế không rõ nguồn.</p>')
    search = fold(f"{aid} {r['title']} {r['target_location']} {r['category_label']}")
    readiness = 'ready' if r['ready_for_owner_review'] else 'needs-info'
    readiness_label = 'Đủ tài liệu để duyệt' if r['ready_for_owner_review'] else 'Cần bổ sung trước khi xuất bản'
    source_links = ''.join(f'<li><a href="{e(s["url"])}" target="_blank" rel="noopener noreferrer">{e(s["title"])}</a><p>{e(s["supports"])}</p></li>' for s in r['sources'])
    cards.append(f'''<article class="exp-card" id="place-{aid}" data-id="{aid}" data-location="{r['target_location_id']}" data-photo-state="{r['photo_state']}" data-readiness="{readiness}" data-search="{e(search)}">
<div class="exp-cover">{cover}</div><div class="exp-card-body">
<div class="review-badges"><span class="exp-badge">ID {aid} · Bản nháp</span><span class="exp-badge">{e(r['category_label'])}</span></div>
<h3>{e(r['title'])}</h3><p class="exp-card-place">{e(r['target_location'])} · nhóm điểm đến</p><p class="review-readiness">{readiness_label}</p>
<p class="exp-card-summary">{e(r['short_description'])}</p>{credit_html}
<details><summary>Duyệt nội dung và đối chiếu</summary><div class="review-detail">
<h4>Nội dung sau rà soát</h4><p>{e(r['short_description'])}</p><p>{e(r['visitor_note'])}</p>
<dl><dt>Địa phương đang lưu trong database</dt><dd>{e(r['current_location'])} (ID {r['current_location_id']})</dd>
<dt>Địa phương đúng theo kế hoạch 5C.1</dt><dd>{e(r['target_location'])} (ID {r['target_location_id']}) — chưa cập nhật</dd>
<dt>Danh mục trong gói → danh mục đã chuẩn hóa</dt><dd><code>{e(r['raw_category'])}</code> → <code>{e(r['proposed_existing_category'])}</code> · đúng giá trị module hỗ trợ</dd>
<dt>Loại bản ghi giữ nguyên</dt><dd>{e(r['item_type'])}</dd></dl>
<h4>Nguồn kiểm chứng nội dung</h4><ul class="review-sources">{source_links or '<li>Chưa có nguồn cho địa điểm cụ thể.</li>'}</ul>
<p>Địa chỉ tham khảo, chờ duyệt: {e(r['reference_address']) or 'Chưa chốt; không suy đoán số nhà/phường'}</p>
<h4>Điều chỉnh và phần còn thiếu</h4><ul>{''.join('<li>'+e(n)+'</li>' for n in r['editorial_changes'] + r['notes'])}</ul>
<h4>Ảnh và ghi công</h4>{source_detail}
<details class="review-original"><summary>Bản nháp gốc trong ZIP</summary><p>{e(r['original_short_description'])}</p><p>{e(r['original_visitor_note'])}</p></details>
<details class="review-existing"><summary>Đối chiếu nội dung đang lưu</summary><p>{e(r['current_description']) or 'Chưa có mô tả'}</p>
<p>Địa chỉ đang lưu (chưa xác minh lại): {e(r['current_address']) or 'Chưa có'}</p></details>
</div></details></div></article>''')

base_css = (ROOT / 'resources/views/experiences/styles.blade.php').read_text(encoding='utf-8')
assert base_css.strip().startswith('<style>') and base_css.strip().endswith('</style>') and '{{' not in base_css
base_css = base_css.strip()[7:-8]
css = base_css + '''
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;line-height:1.6;background:#F8F8F8}button,input,select{font:inherit}button,summary{cursor:pointer}a{color:#2563EB;overflow-wrap:anywhere}p{margin:0 0 12px}h2,h3,h4{margin:0}ul{padding-left:20px}code{font-size:.88em;overflow-wrap:anywhere}.review-top{background:#142B49;color:white;padding:16px 24px}.review-top div{max-width:1184px;margin:auto;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}.review-top strong{letter-spacing:.15em}.review-top span{color:#D8E5F6;font-size:13px}.review-intro{padding:40px 0 24px;max-width:850px}.exp-page .review-intro h1{font-size:clamp(30px,4vw,48px)}.review-intro .exp-intro{max-width:800px}.review-status{display:flex;gap:12px;flex-wrap:wrap;margin:20px 0}.review-status span{padding:9px 14px;border-radius:10px;background:white;border:1px solid #E4E8EE;font-size:14px}.review-alert{padding:20px 24px;border-radius:16px;background:#FFF7E8;border:1px solid #F4D9A8;margin-bottom:24px;font-size:14px}.review-alert p:last-child{margin-bottom:0}.review-links{display:flex;flex-wrap:wrap;gap:16px;font-size:14px;margin-bottom:24px}.review-filters{grid-template-columns:1.5fr 1fr 1fr auto}.review-badges{display:flex;gap:8px;flex-wrap:wrap}.review-credit{font-size:12px;line-height:1.7;color:#64758B;overflow-wrap:anywhere}.review-photo{display:block;border:0;padding:0;width:100%;height:100%;background:transparent}.review-photo img{display:block}.exp-no-image{text-align:center;padding:20px}.review-detail{font-size:13px;line-height:1.8;overflow-wrap:anywhere}.review-detail h4{font-size:14px;margin:18px 0 8px}.review-detail dt{color:#64758B;margin-top:12px}.review-detail dd{margin:0;font-weight:600}.review-detail p{white-space:pre-line}.review-detail li{margin-bottom:10px}.review-detail .credit-text{font-size:12px;background:#F8F8F8;border-radius:8px;padding:12px}.exp-card details{margin-top:16px;border-top:1px solid #E4E8EE;padding-top:14px}.exp-card summary{font-size:13px;font-weight:700;color:#2563EB;min-height:30px}.exp-grid{align-items:start}.exp-card:hover{transform:none}.review-footer{margin-top:40px;border-top:1px solid #E4E8EE;padding-top:24px;color:#64758B;font-size:13px}.review-lightbox{max-height:95dvh;overflow:auto}.review-lightbox h2{font-size:19px}.review-lightbox .review-credit{margin-top:12px}.review-lightbox img{object-fit:contain}.review-count{font-weight:700}.review-existing{font-weight:400}noscript{display:block;padding:16px;border:1px solid #F4D9A8}
@media(max-width:1023px){.review-filters{grid-template-columns:1fr 1fr}.review-filters>.exp-field:first-child{grid-column:1/-1}}
@media(max-width:639px){.review-intro{padding-top:28px}.review-filters{grid-template-columns:1fr}.review-filters>.exp-field:nth-child(2){grid-column:auto;grid-row:auto}.review-status{gap:8px}.review-status span{font-size:12px}.review-alert{padding:16px}.review-photo img{object-fit:cover}.exp-results-heading h2{font-size:22px}}
.review-readiness{font-size:12px;color:#52667E;margin-top:10px}.review-filters{grid-template-columns:1.4fr 1fr 1fr 1fr auto}.review-sources p{font-size:12px;margin-top:5px}.review-gaps{margin-top:24px}.review-gaps summary{font-weight:700;cursor:pointer}.review-gaps li{margin-bottom:8px}.review-gaps a{font-size:13px}
@media(max-width:1023px){.review-filters{grid-template-columns:1fr 1fr}}
@media(max-width:639px){.review-filters{grid-template-columns:1fr}.review-filters>.exp-field:first-child{grid-column:auto}}
'''
photo_json = json.dumps({str(r['id']): {'title': r['title'], 'url': f'../images/optimized/{r["id"]:02d}.jpg', 'credit': r['credit']}
                         for r in rows if r['credit']}, ensure_ascii=False).replace('<', '\\u003c')
script = '''
const photos=PHOTO_DATA;
const cards=[...document.querySelectorAll('.exp-card')];
const query=document.querySelector('#review-query'), locationFilter=document.querySelector('#review-location'), photoFilter=document.querySelector('#review-photo-state'), readinessFilter=document.querySelector('#review-readiness');
const fold=s=>s.toLowerCase().replaceAll('đ','d').normalize('NFD').replace(/[\\u0300-\\u036f]/g,'');
function filter(){const q=fold(query.value.trim());let n=0;for(const card of cards){card.hidden=!!((q&&!card.dataset.search.includes(q))||(locationFilter.value&&card.dataset.location!==locationFilter.value)||(photoFilter.value&&card.dataset.photoState!==photoFilter.value)||(readinessFilter.value&&card.dataset.readiness!==readinessFilter.value));if(!card.hidden)n++;}document.querySelector('#review-count').textContent=n+' / 21 địa điểm';document.querySelector('#review-empty').hidden=n>0;}
for(const field of [query,locationFilter,photoFilter,readinessFilter])field.addEventListener('input',filter);
document.querySelector('#review-reset').addEventListener('click',()=>{query.value='';locationFilter.value='';photoFilter.value='';readinessFilter.value='';filter();query.focus();});
const dialog=document.querySelector('#review-lightbox');let trigger;
for(const button of document.querySelectorAll('[data-photo]'))button.addEventListener('click',()=>{trigger=button;const p=photos[button.dataset.photo],c=p.credit;document.querySelector('#lightbox-title').textContent=p.title;const image=document.querySelector('#lightbox-image');image.src=p.url;image.alt=p.title;document.querySelector('#lightbox-credit').textContent=c.image_source_note;dialog.showModal();});
document.querySelector('#lightbox-close').addEventListener('click',()=>dialog.close());
dialog.addEventListener('close',()=>trigger?.focus());
filter();
'''.replace('PHOTO_DATA', photo_json)
hash64 = lambda s: base64.b64encode(hashlib.sha256(s.encode('utf-8')).digest()).decode('ascii')
csp = f"default-src 'none'; img-src 'self' file: data:; style-src 'sha256-{hash64(css)}'; script-src 'sha256-{hash64(script)}'; base-uri 'none'; form-action 'none'; object-src 'none'"
missing_count = summary['without_prepared_photo']
ready_count = summary['ready_for_owner_review']
need_count = len(rows) - ready_count
gap_links = ''.join(f'<li><a href="#place-{r["id"]}">ID {r["id"]} · {e(r["title"])}</a> — {e("; ".join(edits[r["id"]]["issues"]))}</li>' for r in rows if not r['ready_for_owner_review'])
page = f'''<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="{e(csp)}"><title>AUREL — Duyệt 21 nội dung Trải nghiệm</title><style>{css}</style></head>
<body><header class="review-top"><div><strong>AUREL</strong><span>Bản xem trước riêng · chưa đưa lên website</span></div></header>
<main class="exp-page"><div class="exp-wrap"><section class="review-intro"><div class="exp-eyebrow">Hồ sơ nội dung chờ duyệt</div>
<h1>21 trải nghiệm.<br><span>Duyệt trước khi cập nhật.</span></h1><p class="exp-intro">Nội dung đã rà soát và danh mục đã chuẩn hóa. Bản gốc được giữ để đối chiếu. Xem ảnh, nguồn kiểm chứng và ghi công tại từng địa điểm; mọi bản ghi vẫn chờ bạn duyệt.</p>
<div class="review-status"><span><b>21</b> danh mục đúng chuẩn</span><span><b>{len(images)}</b> ảnh · +{summary['new_photos']} ảnh mới</span><span><b>{missing_count}</b> địa điểm thiếu ảnh</span><span><b>0</b> cập nhật database</span></div><a href="#draft-list">Xem 21 bản nháp ↓</a></section>
<aside class="review-alert"><p><b>{ready_count} địa điểm đủ tài liệu để duyệt; {need_count} địa điểm cần bổ sung.</b> Đây là trạng thái hồ sơ nội dung, chưa phải phê duyệt xuất bản. Tất cả 21 bản ghi vẫn ở bản nháp.</p>
<p>Đã chuẩn hóa danh mục theo module hiện có, giữ nguyên attraction/restaurant/activity và 7 ảnh đã đạt. ID 20 dùng ảnh thay thế có nguồn rõ ràng; ảnh cũ bị cảnh báo vẫn bị loại.</p>
<p>Địa phương dùng để lọc theo kế hoạch 5C.1; 21 liên kết cũ trong database chưa thay đổi. Giá, tọa độ và lịch hoạt động chưa được đưa vào dữ liệu đã duyệt.</p></aside>
<nav class="review-links" aria-label="Tài liệu đối chiếu"><a href="reconciliation.csv">Đối chiếu 21 địa điểm</a><a href="normalized-drafts.json">Nội dung chuẩn hóa</a><a href="CONTENT-REVIEW.md">Rà soát và nguồn nội dung</a><a href="ATTRIBUTIONS.md">Ghi công ảnh</a><a href="publication-gaps.csv">Danh sách cần bổ sung</a></nav>
<div class="exp-filters review-filters"><div class="exp-field"><label for="review-query">Tên hoặc ID địa điểm</label><input id="review-query" type="search" placeholder="Tìm nội dung cần duyệt" autocomplete="off"></div>
<div class="exp-field"><label for="review-location">Địa phương đề xuất</label><select id="review-location"><option value="">Tất cả địa phương</option>{''.join(f'<option value="{lid}">{e(locations[lid]["name"])}</option>' for lid in [1,2,7])}</select></div>
<div class="exp-field"><label for="review-photo-state">Tình trạng ảnh</label><select id="review-photo-state"><option value="">Tất cả</option><option value="prepared">Đã chuẩn bị ({len(images)})</option><option value="missing">Chưa đủ điều kiện ({missing_count})</option></select></div>
<div class="exp-field"><label for="review-readiness">Hồ sơ nội dung</label><select id="review-readiness"><option value="">Tất cả</option><option value="ready">Đủ tài liệu để duyệt ({ready_count})</option><option value="needs-info">Cần bổ sung ({need_count})</option></select></div>
<button id="review-reset" class="exp-button exp-button--quiet" type="button">Xóa bộ lọc</button></div>
<details class="review-alert review-gaps"><summary>{need_count} địa điểm chưa đủ nội dung hoặc ảnh để xuất bản</summary><ul>{gap_links}</ul><p>Các địa điểm còn lại cũng chỉ được nhập/xuất bản sau khi chủ dự án duyệt và đáp ứng điều kiện tích hợp.</p></details>
<noscript>JavaScript đang tắt. Bạn vẫn có thể đọc đủ 21 bản nháp và mở phần đối chiếu bên dưới; bộ lọc và cửa sổ ảnh cần JavaScript.</noscript>
<div class="exp-results-heading" id="draft-list"><h2>Nội dung và ảnh chờ duyệt</h2><p id="review-count" class="review-count" role="status" aria-live="polite">21 / 21 địa điểm</p></div>
<div class="exp-state" id="review-empty" hidden><h2>Không có nội dung phù hợp</h2><p>Thử tên khác hoặc xóa bộ lọc.</p></div><section class="exp-grid" aria-label="21 bản nháp">{''.join(cards)}</section>
<footer class="review-footer"><p>Dùng snapshot catalog chỉ đọc lúc {e(live['identity']['observed_at'])} (UTC+7), 25/10/2026. Vòng bổ sung này không kết nối database. Không tạo địa phương, không xuất bản, không upload ảnh vào ứng dụng.</p>
<p>Các ảnh được đổi kích thước/nén JPEG, bỏ EXIF, không cắt ảnh gốc. Nhấn ảnh để xem toàn khung. Giữ giấy phép và ghi công tương ứng khi sử dụng.</p></footer></div></main>
<dialog id="review-lightbox" class="exp-lightbox review-lightbox"><div class="exp-lightbox-bar"><h2 id="lightbox-title">Ảnh chờ duyệt</h2><button id="lightbox-close" class="exp-button exp-button--quiet" type="button">Đóng</button></div>
<img id="lightbox-image" alt=""><p id="lightbox-credit" class="review-credit"></p></dialog><script>{script}</script></body></html>'''
(OUT / 'index.html').write_text(page, encoding='utf-8')
(OUT / 'README.md').write_text('''# Bộ duyệt nội dung AUREL

Mở `review/index.html` bằng Chrome. Không cần chạy Laravel hoặc kết nối database.
Đây là tài liệu duyệt; không có chức năng ghi dữ liệu hoặc upload.

Chủ dự án duyệt: nội dung từng ID; địa phương đề xuất; danh mục chuyển đổi;
phạm vi ảnh, tác giả/giấy phép/ghi công; nguồn xác minh địa điểm còn thiếu.
Không xuất bản tự động. Các giá trị trống không phải yêu cầu xóa dữ liệu cũ.

Chỉ sau khi duyệt mới lập thao tác cập nhật cụ thể trong Admin đang có,
kiểm tra điều kiện schema/backup và xin phê duyệt ghi database riêng.

ZIP nguồn/script đi kèm không được thực thi. Không có credential trong gói duyệt.
''', encoding='utf-8')
save_json(WORK / 'evidence/reconciliation-summary.json', summary)

bundle = WORK / 'aurel-experiences-owner-review.zip'
with zipfile.ZipFile(bundle, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=6) as archive:
    for file in sorted(OUT.iterdir()):
        if file.is_file():
            archive.write(file, 'review/' + file.name)
    for aid in sorted(images):
        for ext in ['jpg', 'license.txt']:
            file = WORK / 'images/optimized' / f'{aid:02d}.{ext}'
            archive.write(file, f'images/optimized/{file.name}')
print(json.dumps({'summary': summary, 'preview': str(OUT / 'index.html'), 'bundle_bytes': bundle.stat().st_size,
                  'bundle_sha256': sha(bundle)}, ensure_ascii=True))
