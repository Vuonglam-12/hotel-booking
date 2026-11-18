"""Keep actual OTA stay totals and explicitly identify any DEMO average-night allocation."""
import hashlib,json,pathlib,re
from decimal import Decimal
ROOT=pathlib.Path(__file__).resolve().parents[1];WORK=ROOT/'storage/app/private/room-catalog-update-20261031'
source=WORK/'browser/expedia-54-text.txt';text=source.read_text(encoding='utf8')
assert '15 thg 11' in text[:1800] and '17 thg 11' in text[:1800] and '2 khách, 1 phòng' in text[:1800]
assert 'Melia Vinpearl Huế' in text[:1800] and '50A Hùng Vương' in text[:1800]
offers={}
for section in text.split('Xem tất cả ảnh ')[1:]:
    if 'Giá hiện tại là' not in section:continue
    name=section.split('\n')[0].strip();price=re.search(r'Giá hiện tại là\s+([\d.,\s]+)\s*₫',section);total=re.search(r'Tổng\s+([\d.,\s]+)\s*₫',section)
    if not price or not total or 'bao gồm thuế' not in section:continue
    offers[name]={'total':int(re.sub(r'\D','',total[1])),'display':int(re.sub(r'\D','',price[1])),'text':section[:section.find('Đặt\nĐặt') if 'Đặt\nĐặt' in section else 3500]}
names={137:'Phòng Deluxe',138:'Phòng Premium',139:'Phòng Suite',339:'Premium Room With View',340:'Phòng Suite, 2 phòng ngủ',341:'Phòng Suite Presidential',342:'Phòng Deluxe, 2 phòng ngủ'}
audit=json.loads((ROOT/'storage/app/private/room-catalog-readiness-20261031/audit.json').read_text(encoding='utf8'));hotel=next(h for h in audit['hotels'] if h['id']==54)
records=[]
for owner,caption in names.items():
    offer=offers[caption];row=next(r for r in hotel['types'] if r['id']==owner)
    meal_text=offer['text'].split('Bổ sung')[0];breakfast_included='Bữa sáng' in meal_text
    records.append({'hotel_id':54,'hotel_room_type_id':owner,'room_type_id':row['room_type_id'],'name':row['name'],'source_room_name':caption,
      'source_url':'https://www.expedia.com.vn/Hue-Khach-San-Melia-Vinpearl-Hue.h29267758.Thong-tin-khach-san?startDate=2026-11-15&endDate=2026-11-17&d1=2026-11-15&d2=2026-11-17&adults=2&rooms=1',
      'checked_on':'2026-10-31','prepared_on':'2026-11-01','evidence_path':'storage/app/private/room-catalog-update-20261031/browser/expedia-54-text.txt','evidence_sha256':hashlib.sha256(source.read_bytes()).hexdigest(),
      'check_in':'2026-11-15','check_out':'2026-11-17','nights':2,'adults':2,'children':0,'rooms':1,'currency':'VND','taxes_and_fees_included':True,
      'stay_total_vnd':str(offer['total']),'source_display_price_vnd':str(offer['display']),
      'nightly_average_vnd':str((Decimal(offer['total'])/2).quantize(Decimal('0.01'))),
      'breakfast_included':breakfast_included,'cancellation_display':[line for line in offer['text'].splitlines() if 'Hoàn tiền' in line or 'Trước' in line],
      'demo_allocation':{'kind':'season','start_date':'2026-11-15','end_date':'2026-11-16','label':'Giá bình quân kỳ 15–17/11, 2 khách (gồm thuế/phí)',
          'method':'Verified two-night stay total divided by two; an average reference allocation for this DEMO period, not an OTA-provided nightly breakdown.'},
      'conditions_excerpt':offer['text'],'inventory_imported':False})
manifest={'checked_on':'2026-10-31','prepared_on':'2026-11-01','data_kind':'verified_stay_quotes_with_explicit_demo_average_allocation','records':records,
  'limits':['Prices are reference quotes for the exact two-night stay and occupancy only.','Do not turn the average into an unrestricted base price or a verified OTA nightly breakdown.','No physical room availability/count is imported from the OTA.']}
(ROOT/'database/data/verified-room-rate-references-2026-11-01.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n',encoding='utf8')
print(json.dumps({'verified_stay_quotes':len(records),'hotel':54,'period':'2026-11-15/2026-11-17','currency':'VND','new_fixed_base_prices':0,'source_total_preserved':True}))
