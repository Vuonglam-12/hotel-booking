"""Export previously downloaded/verified Commons photos and draft content.
No Laravel, database, network, image re-encoding or modification of the review source.
"""
import base64
import copy
import csv
import hashlib
import html
import json
from pathlib import Path
import sys
import urllib.parse
import uuid
import zipfile

ROOT = Path(__file__).resolve().parents[1]
WORK = Path(sys.argv[1]).resolve()
if WORK.parent != Path(r'C:\Users\ASUS\AppData\Local\AUREL\content-review').resolve():
    raise SystemExit('An explicit private review directory is required.')
OUT = WORK / 'exports' / ('commons-20261025-' + uuid.uuid4().hex[:8])
OUT.mkdir(parents=True, exist_ok=False)
(OUT / 'images').mkdir()
(OUT / 'content').mkdir()
(OUT / 'review').mkdir()


def read(relative):
    return json.loads((WORK / relative).read_text(encoding='utf-8-sig'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def save(path, value):
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


def esc(value):
    return html.escape(str(value or ''), quote=True)


def table(path, rows, columns):
    with path.open('w', encoding='utf-8-sig', newline='') as handle:
        writer = csv.DictWriter(handle, fieldnames=columns, extrasaction='ignore')
        writer.writeheader()
        writer.writerows(rows)


rights = {r['attraction_id']: r for r in read('evidence/photo-rights-review.json')}
optimized = {r['attraction_id']: r for r in read('evidence/image-optimization.json')}
current = {r['id']: r for r in read('evidence/commons-export-current-checks.json')}
credits = {r['attraction_id']: r for r in read('review/photo-credits.json')}
review = read('review/review-data.json')['records']
normalized = copy.deepcopy(read('review/normalized-drafts.json'))
assert len(review) == len(normalized['records']) == 21
assert all(r['publish_status'] == 'draft' for r in normalized['records'])
before = {aid: {'sha256': sha(Path(row['file'])), 'mtime_ns': Path(row['file']).stat().st_mtime_ns}
          for aid, row in optimized.items()}
exported, skipped = [], []
for item in review:
    aid = item['id']
    source = rights.get(aid)
    if not source:
        skipped.append({'attraction_id': aid, 'title': item['title'], 'reason': 'Chưa có ảnh đúng địa điểm được xác minh quyền sử dụng.', 'source_page': ''})
        continue
    if urllib.parse.urlsplit(source['source_page']).hostname != 'commons.wikimedia.org':
        skipped.append({'attraction_id': aid, 'title': item['title'], 'reason': 'Nguồn Flickr đã kiểm tra ở vòng 2, nằm ngoài phạm vi gói Wikimedia Commons; không phải lỗi tải.', 'source_page': source['source_page']})
        continue
    try:
        assert source['status'] == 'downloaded_license_checked' and not source['source_review_warning']
        check = current[aid]
        assert all(check[k] for k in ['source_sha1_match', 'source_sha256_match', 'revision_unchanged', 'license_unchanged', 'jpeg'])
        original = Path(source['source_file']).resolve()
        assert original.is_relative_to((WORK / 'images/source').resolve())
        assert hashlib.sha1(original.read_bytes()).hexdigest() == source['original_sha1'].zfill(40)
        image = optimized[aid]
        image_path = Path(image['file']).resolve()
        assert image_path.is_relative_to((WORK / 'images/optimized').resolve())
        blob = image_path.read_bytes()
        assert len(blob) < 500000 and blob[:2] == b'\xff\xd8' and blob[-2:] == b'\xff\xd9'
        assert sha(image_path) == image['sha256']
        destination = OUT / 'images' / f'{aid}.jpg'
        credit = credits[aid]
        scope_note = ('Theo nguồn: tác phẩm chính phủ liên bang, public domain tại Hoa Kỳ/PDM. '
                      'Ảnh tư liệu 23/05/2016; cần chủ dự án duyệt phạm vi sử dụng, không ngụ ý bảo trợ AUREL.'
                      if aid == 5 else 'Giữ ghi công, liên kết giấy phép và thông báo thay đổi theo nguồn; bản ảnh chưa được chủ dự án duyệt xuất bản.')
        record = {'attraction_id': aid, 'title': item['title'], 'location': item['target_location'],
                  'file': f'images/{aid}.jpg', 'bytes': len(blob), 'width': image['width'], 'height': image['height'],
                  'source_page': source['source_page'], 'original_download_url': source['download_url'],
                  'photo_title': credit['photo_title'], 'author': source['author'], 'attribution': credit['attribution'],
                  'license': source['license'], 'license_url': credit['license_url'], 'changes': credit['changes'],
                  'photo_date': source['photo_date'], 'source_revision': source['revision']['revid'],
                  'original_sha1': source['original_sha1'], 'source_sha256': source['source_sha256'],
                  'sha256': image['sha256'], 'credit_text': credit['image_source_note'], 'usage_note': scope_note}
        destination.write_bytes(blob)
        assert sha(destination) == image['sha256']
        (OUT / 'images' / f'{aid}.license.txt').write_text(credit['image_source_note'] + '\n' + scope_note + '\n', encoding='utf-8')
        exported.append(record)
    except (AssertionError, KeyError, OSError) as error:
        skipped.append({'attraction_id': aid, 'title': item['title'], 'reason': 'Không vượt qua kiểm tra nguồn/file/hash/kích thước: ' + (str(error) or type(error).__name__), 'source_page': source['source_page']})

byid = {r['attraction_id']: r for r in exported}
for record in normalized['records']:
    aid = record['existing_attraction_id']
    record['image_file'] = byid[aid]['file'] if aid in byid else None
    record['image_source'] = byid[aid]['credit_text'] if aid in byid else None
    record['photo_export_status'] = 'EXPORTED_COMMONS' if aid in byid else 'NOT_INCLUDED'
    record['needs_additional_material'] = record['content_status'] != 'supported' or aid not in byid
normalized['source_scope'] = 'WIKIMEDIA_COMMONS_ONLY'
normalized['approval'] = 'PENDING_OWNER_REVIEW'
save(OUT / 'content/experiences_21_draft.json', normalized)
table(OUT / 'content/experiences_21_draft.csv', [
    {'attraction_id': r['existing_attraction_id'], 'title': r['title'], 'location_id_proposed': r['location_id_proposed'],
     'category': r['experience_category'], 'summary': r['experience_summary'], 'description': r['description'],
     'reference_address_not_approved': r['reference_address_not_approved'], 'image_file': r['image_file'],
     'content_status': r['content_status'], 'publish_status': r['publish_status'],
     'source_urls': ' | '.join(s['url'] for s in r['reference_sources'])} for r in normalized['records']],
    ['attraction_id','title','location_id_proposed','category','summary','description','reference_address_not_approved','image_file','content_status','publish_status','source_urls'])
save(OUT / 'sources.json', exported)
table(OUT / 'sources.csv', exported,
      ['attraction_id','title','location','file','bytes','width','height','photo_title','author','attribution',
       'source_page','original_download_url','license','license_url','photo_date','changes','usage_note','sha256'])
table(OUT / 'skipped.csv', skipped, ['attraction_id','title','reason','source_page'])
credit_md = ['# Nguồn và ghi công ảnh Wikimedia Commons', '',
             'Bộ ảnh chờ chủ dự án duyệt; chưa upload, nhập database hoặc xuất bản.', '']
for r in exported:
    credit_md += [f"## {r['attraction_id']}.jpg — {r['title']}", '', r['credit_text'], '', r['usage_note'], '',
                  f"Ngày ảnh: {r['photo_date']}; revision Commons: {r['source_revision']}; SHA-256 JPEG: {r['sha256']}", '']
(OUT / 'ATTRIBUTIONS.md').write_text('\n'.join(credit_md), encoding='utf-8')

# Reuse AUREL's existing experience CSS for a portable, script-free review.
css = (ROOT / 'resources/views/experiences/styles.blade.php').read_text(encoding='utf-8').strip()
assert css.startswith('<style>') and css.endswith('</style>') and '{{' not in css
css = css[7:-8] + '''
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;line-height:1.65;background:#F8F8F8}a{color:#2563EB;overflow-wrap:anywhere}p{margin:8px 0 14px}h2{font-size:24px}h4{margin:18px 0 8px}code{overflow-wrap:anywhere}summary{color:#2563EB;cursor:pointer;font-size:14px;font-weight:700}details{margin-top:16px}.pack-heading{padding:30px 0 12px}.pack-heading h1{font-size:clamp(28px,4vw,44px)}.pack-note{padding:18px;background:white;border:1px solid #E4E8EE;border-radius:14px;margin:20px 0}.pack-links{display:flex;flex-wrap:wrap;gap:12px;font-size:14px}.exp-grid{align-items:start}.exp-card:hover{transform:none}.pack-credit{font-size:12px;color:#64758B;overflow-wrap:anywhere}.pack-detail{font-size:14px;white-space:pre-line;overflow-wrap:anywhere}.exp-cover img{object-fit:contain;background:#EDF1F6}.exp-cover a{display:block;height:100%}.exp-no-image{padding:20px;text-align:center}.pack-footer{padding-top:30px;color:#64758B;font-size:13px}.pack-index{display:flex;gap:10px;flex-wrap:wrap;font-size:14px}ul{padding-left:20px}
'''
cards = []
for r in review:
    aid = r['id']
    image = byid.get(aid)
    if image:
        cover = f'<a href="../images/{aid}.jpg" target="_blank" rel="noopener"><img src="../images/{aid}.jpg" alt="{esc(r["title"])}" width="{image["width"]}" height="{image["height"]}"></a>'
        credit_html = (f'<p class="pack-credit">{esc(image["attribution"])} · '
                       f'<a href="{esc(image["source_page"])}" target="_blank" rel="noopener noreferrer">Trang nguồn</a> · '
                       f'<a href="{esc(image["license_url"])}" target="_blank" rel="noopener noreferrer">{esc(image["license"])}</a><br>'
                       f'{esc(image["changes"])}<br>{esc(image["usage_note"])}</p>')
    else:
        why = next(x['reason'] for x in skipped if x['attraction_id'] == aid)
        cover = '<div class="exp-no-image">Chưa có ảnh Commons trong gói</div>'
        credit_html = f'<p class="pack-credit">{esc(why)}</p>'
    sources = ''.join(f'<li><a href="{esc(s["url"])}" target="_blank" rel="noopener noreferrer">{esc(s["title"])}</a><br>{esc(s["supports"])}</li>' for s in r['sources'])
    cards.append(f'''<article class="exp-card" id="place-{aid}" data-id="{aid}"><div class="exp-cover">{cover}</div><div class="exp-card-body">
<span class="exp-badge">ID {aid} · Bản nháp</span><h3>{esc(r['title'])}</h3><p class="exp-card-place">{esc(r['target_location'])} · {esc(r['category_label'])} ({esc(r['proposed_existing_category'])})</p>
<p class="pack-detail">{esc(r['short_description'])}</p>{credit_html}
<details><summary>Nội dung, nguồn và phần cần duyệt</summary><div class="pack-detail"><p>{esc(r['visitor_note'])}</p>
<p>Địa chỉ tham khảo, chờ duyệt: {esc(r['reference_address']) or 'Chưa chốt'}</p><ul>{sources or '<li>Chưa có nguồn cho cơ sở cụ thể.</li>'}</ul>
<ul>{''.join('<li>'+esc(n)+'</li>' for n in r['notes'])}</ul></div></details></div></article>''')
style_hash = base64.b64encode(hashlib.sha256(css.encode('utf-8')).digest()).decode('ascii')
csp = f"default-src 'none'; img-src 'self' file:; style-src 'sha256-{style_hash}'; base-uri 'none'; form-action 'none'; object-src 'none'"
page = f'''<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="{esc(csp)}"><title>AUREL — Ảnh Commons và nội dung chờ duyệt</title><style>{css}</style></head><body><main class="exp-page"><div class="exp-wrap">
<header class="pack-heading"><div class="exp-eyebrow">AUREL · Gói Wikimedia Commons</div><h1>Ảnh và nội dung chờ duyệt</h1><p>{len(exported)} ảnh JPEG dưới 500 KB/ảnh · 21 bản nháp · chưa nhập hệ thống.</p></header>
<nav class="pack-links"><a href="../sources.csv">Nguồn, tác giả và giấy phép</a><a href="../content/experiences_21_draft.csv">Nội dung 21 địa điểm</a><a href="../skipped.csv">Danh sách bỏ qua</a><a href="../ATTRIBUTIONS.md">Ghi công</a></nav>
<div class="pack-note"><b>Chờ chủ dự án duyệt toàn bộ nội dung và ảnh.</b><p>Ảnh được đặt tên theo attraction_id. Ảnh tư liệu không xác nhận giá, thực đơn, cảnh quan hoặc lịch hoạt động hiện tại. Địa phương là mapping đề xuất đã đối chiếu; chưa sửa database.</p><p>{len(skipped)} địa điểm chưa có ảnh Commons trong gói. Riêng ID 14 có ảnh Flickr ở gói vòng 2, được bỏ khỏi gói này theo phạm vi nguồn.</p></div>
<nav class="pack-index" aria-label="Tới địa điểm">{''.join(f'<a href="#place-{r["id"]}">ID {r["id"]}</a>' for r in review)}</nav><h2>Nội dung và ảnh</h2><section class="exp-grid">{''.join(cards)}</section>
<footer class="pack-footer">Xuất ngày 25/10/2026. Tất cả 21 bản ghi giữ draft/needs_owner_approval. Không có chức năng nhập dữ liệu hoặc upload.</footer></div></main></body></html>'''
(OUT / 'review/index.html').write_text(page, encoding='utf-8')
(OUT / 'README.md').write_text(f'''# AUREL — Ảnh Commons và nội dung chờ duyệt

Mở review/index.html bằng Chrome, không cần chạy Laravel/MySQL.

- {len(exported)} JPEG trong images/, tên đúng attraction_id.jpg; mỗi file dưới 500.000 byte.
- sources.csv / sources.json: tên địa điểm, nguồn Commons, tác giả, giấy phép, ghi công, thay đổi, checksum.
- ATTRIBUTIONS.md và từng images/ID.license.txt: thông tin phải giữ khi dùng ảnh.
- content/experiences_21_draft.json / .csv: 21 nội dung đã chuẩn hóa, mọi bản ghi vẫn draft.
- skipped.csv: địa điểm chưa có ảnh trong gói, nêu rõ nguồn ngoài Commons hoặc thiếu nguồn hợp lệ.
- manifest.json / SHA256SUMS.txt: thông tin kiểm chứng file.

Chỉ là bộ duyệt. Không có SQL, importer, credential hoặc thao tác upload/xuất bản.
Không dùng ảnh có cảnh báo nguồn, ảnh sai địa phương hay ảnh All rights reserved.
Ảnh Flickr ID 14 đã chuẩn bị trước đây được giữ nguyên ngoài gói Commons này.
Các ảnh đã tải và nén đạt yêu cầu được kiểm tra lại, sao chép nguyên byte, không tải/nén lại.
Giá trị trống và địa chỉ tham khảo không phải chỉ thị ghi đè dữ liệu cũ.
''', encoding='utf-8')

for aid, old in before.items():
    f = Path(optimized[aid]['file'])
    assert sha(f) == old['sha256'] and f.stat().st_mtime_ns == old['mtime_ns']
summary = {'scope': 'WIKIMEDIA_COMMONS_ONLY', 'client_date': '2026-10-25', 'approval': 'PENDING_OWNER_REVIEW',
           'attractions': 21, 'photos': len(exported), 'photo_ids': sorted(byid), 'skipped_ids': [r['attraction_id'] for r in skipped],
           'jpeg_bytes': sum(r['bytes'] for r in exported), 'max_jpeg_bytes': max((r['bytes'] for r in exported), default=0),
           'all_below_500000_bytes': all(r['bytes'] < 500000 for r in exported), 'existing_images_unchanged': len(before),
           'database_connections': 0, 'database_writes': 0, 'system_uploads': 0,
           'checks': {'source_sha1': 'PASS_FOR_EACH_EXPORTED_COMMONS_IMAGE', 'current_revision_and_license': 'MATCH_PRIOR_REVIEW',
                      'output_sha256': 'PASS', 'existing_file_hash_and_mtime': 'PASS'}, 'assets': exported}
save(OUT / 'manifest.json', summary)
def included_file(path):
    # If an I/O failure left a partial new image, exclude it from the package.
    return path.is_file() and (path.parent != OUT / 'images' or int(path.name.split('.')[0]) in byid)


files = sorted(p for p in OUT.rglob('*') if included_file(p))
(OUT / 'SHA256SUMS.txt').write_text('\n'.join(sha(p) + '  ' + p.relative_to(OUT).as_posix() for p in files) + '\n', encoding='utf-8')
files = sorted(p for p in OUT.rglob('*') if included_file(p))
archive = OUT.parent / (OUT.name + '-anh-va-noi-dung-cho-duyet.zip')
assert not archive.exists()
with zipfile.ZipFile(archive, 'x', compression=zipfile.ZIP_DEFLATED, compresslevel=6) as bundle:
    for file in files:
        bundle.write(file, file.relative_to(OUT).as_posix())
with zipfile.ZipFile(archive) as bundle:
    assert bundle.testzip() is None
    assert len(bundle.namelist()) == len(files)
    for file in files:
        assert hashlib.sha256(bundle.read(file.relative_to(OUT).as_posix())).hexdigest() == sha(file)
result = {'directory': str(OUT), 'preview': str(OUT / 'review/index.html'), 'zip': str(archive),
          'zip_bytes': archive.stat().st_size, 'zip_sha256': sha(archive), 'files': len(files),
          'photos': len(exported), 'photo_ids': sorted(byid), 'skipped_ids': summary['skipped_ids'],
          'jpeg_bytes': summary['jpeg_bytes'], 'max_jpeg_bytes': summary['max_jpeg_bytes'],
          'checks': 'PASS_SOURCE_CHECKSUM_SIZE_FILENAME_CRC_ZIP_ROUNDTRIP_ORIGINALS_UNCHANGED',
          'database_connections': 0, 'database_writes': 0}
save(WORK / 'evidence/commons-export-result.json', result)
print(json.dumps(result, ensure_ascii=True))
