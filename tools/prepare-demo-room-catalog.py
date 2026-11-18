"""User-authorized DEMO capacities/amenities and catalog activation. Never synthesize prices or inventory."""
import hashlib,json,pathlib,re,statistics,unicodedata
ROOT=pathlib.Path(__file__).resolve().parents[1];WORK=ROOT/'storage/app/private/room-catalog-update-20261031'
WORK.mkdir(parents=True,exist_ok=True)
audit=json.loads((ROOT/'storage/app/private/room-catalog-readiness-20261031/audit.json').read_text(encoding='utf8'))
rate_file=ROOT/'database/data/verified-room-rate-references-2026-11-01.json'
real_references=json.loads(rate_file.read_text(encoding='utf8'))['records'] if rate_file.exists() else []
reference_prices={r['hotel_room_type_id']:float(r['nightly_average_vnd']) for r in real_references}
def norm(s):
    s=''.join(c for c in unicodedata.normalize('NFD',s.lower().replace('đ','d')) if unicodedata.category(c)!='Mn')
    s=re.sub(r'[^a-z0-9]+','',s)
    aliases={'wifimienphi':'wifi','wifiinallrooms':'wifi','freewifi':'wifi','dieuhoakhongkhi':'dieuhoa','airconditioning':'dieuhoa','maylanh':'dieuhoa','tivi':'tv','flatscreentv':'tv','safetybox':'ketantoan','safetydepositbox':'ketantoan',
      'nhahang':'restaurant','hoboi':'pool','hoboingoaitroi':'pool','quaybar':'bar','phonggym':'gym','trungtamtheduc':'gym','trungtamthehinh':'gym',
      'baidauxe':'parking','baidoxe':'parking','chodoxe':'parking','baidauxemienphi':'parking','baidauxetaicho':'parking',
      'duadonsanbay':'airportshuttle','buasang':'breakfast','buasangbuffet':'breakfast','dichvuphucvuphong':'roomservice','phucvuphong':'roomservice',
      'luugiuhanhly':'giuhanhly','dichvugiuhanhly':'giuhanhly','letan24gio':'letan24gio','bantieptan24gio':'letan24gio','letan24h':'letan24gio',
      'dichvudonphonghangngay':'donphonghangngay','mayphacapheespresso':'mayphacaphe','ketantoantrongphong':'ketantoan','tudo':'tuquanao'}
    if 'wifi' in s:return 'wifi'
    return aliases.get(s,s)
def merge(old,extra):
    result=list(old);seen={norm(x) for x in old}
    for x in extra:
        if norm(x) not in seen:result.append(x);seen.add(norm(x))
    return result
def configured_capacity(name,bed=None):
    name=unicodedata.normalize('NFKD',name.lower());plain=''.join(c for c in name if not unicodedata.combining(c)).replace('đ','d')
    if re.search(r'dorm|tap the|bunk bed in|single bed in',plain):return 1,'one guest per DEMO dorm booking unit'
    bedroom=re.search(r'(one|two|three|four|five|[1-5])[- ]bedroom|([1-5])\s*phong ngu',plain)
    if bedroom:
        v=bedroom[1] or bedroom[2];number={'one':1,'two':2,'three':3,'four':4,'five':5}.get(v,v)
        return min(10,int(number)*2),'DEMO capacity of two guests per explicitly named bedroom'
    if re.search(r'triple|phong ba\b',plain):return 3,'DEMO triple configuration'
    if re.search(r'quadruple|quad room',plain):return 4,'DEMO quadruple configuration'
    if re.search(r'family|connecting|connected|duplex|gia dinh',plain):return 4,'DEMO family/connecting configuration'
    if bed:
        bed=re.sub(r'\([^)]*\)','',bed.lower())
        for word,num in [('one','1'),('two','2'),('three','3'),('four','4')]:bed=re.sub(r'\b'+word+r'\b',num,bed)
        if not re.search(r'\bor\b|hoặc|/',bed):
            big=sum(int(n)*2 for n in re.findall(r'(\d+)\s*(?:king|queen|double)',bed))
            small=sum(int(n) for n in re.findall(r'(\d+)\s*(?:single|twin)',bed))
            if big+small:return min(10,big+small),'DEMO occupancy configured from the described bedding; not a verified real-world maximum'
    return 2,'DEMO two-guest configuration'
cloudbeds_path=WORK/'browser/cloudbeds-1-text.txt'
verified_capacity={}
if cloudbeds_path.exists():
    text=cloudbeds_path.read_text(encoding='utf8');rooms=json.JSONDecoder().raw_decode(text)[0].get('containsPlace',[])
    for room in rooms:
        name=room['name'][0]['@value'];maximum=room.get('occupancy',{}).get('maxValue')
        if name in ['Deluxe Double','Meander Suite','Deluxe Triple','Quadruple Room'] and isinstance(maximum,int):verified_capacity[norm(name)]=maximum
catalog=[];hotels=[]
for hotel in audit['hotels']:
    active=[x for x in hotel['types'] if x['status']!='archived']
    prices=[float(x['base_price']) for x in active if x['base_price'] is not None]
    median=statistics.median(prices) if prices else None
    for old in active:
        room_name=old['name'].lower();basis=reference_prices.get(old['id'],float(old['base_price']) if old['base_price'] is not None else median)
        tier=0 if basis is None or basis<800000 else 1 if basis<1800000 else 2 if basis<4000000 else 3
        dorm=bool(re.search('dorm|tập thể',room_name))
        extra=['Wi-Fi','Điều hòa','Khăn tắm','Ổ cắm điện gần giường']
        if dorm:extra+=['Tủ khóa cá nhân','Đèn đọc sách cá nhân','Phòng tắm dùng chung']
        else:
            extra+=['TV','Phòng tắm riêng','Dép đi trong phòng','Nước uống đóng chai']
            if tier>=1:extra+=['Máy sấy tóc','Ấm đun nước','Tủ quần áo','Bàn làm việc']
            if tier>=2:extra+=['Két an toàn','Minibar','Bộ trà và cà phê','Áo choàng tắm']
            if tier>=3:extra+=['Máy pha cà phê','Khu vực tiếp khách','Bàn ủi và cầu ủi']
        if re.search(r'suite|villa|residence|apartment|căn hộ|biệt thự',room_name):extra+=['Khu vực tiếp khách']
        if re.search(r'pool villa|pool suite|pool hut',room_name):extra+=['Hồ bơi riêng']
        if 'balcony' in room_name:extra+=['Ban công riêng']
        capacity,reason=configured_capacity(old['name'],old['bed_type']) if old['capacity'] is None else (old['capacity'],'Existing value preserved')
        source=None
        if old['capacity'] is None and hotel['id']==1 and norm(old['name']) in verified_capacity:
            capacity=verified_capacity[norm(old['name'])];reason='Explicit occupancy.maxValue in the official Cloudbeds HotelRoom metadata'
            source={'source_url':'https://hotels.cloudbeds.com/en/reservation/BscTin?currency=VND&checkin=2026-11-15&checkout=2026-11-17&adults=2','checked_on':'2026-10-31','sha256':hashlib.sha256(cloudbeds_path.read_bytes()).hexdigest()}
        amenities=merge(old['amenities'],extra)
        amenities=amenities[:max(50,len(old['amenities']))]
        catalog.append({'id':old['id'],'hotel_id':hotel['id'],'room_type_id':old['room_type_id'],'name':old['name'],
          'before':{k:old[k] for k in ['capacity','status','base_price','amenities']},
          'after':{'capacity':capacity,'status':'active','base_price':old['base_price'],'amenities':amenities},
          'demo_capacity':old['capacity'] is None and source is None,'capacity_source':source,'capacity_basis':reason,'amenity_tier':tier,'amenity_tier_basis':'Verified OTA stay reference average' if old['id'] in reference_prices else 'Existing project room price' if old['base_price'] is not None else 'Existing project hotel median price; not a newly estimated price',
          'amenities_added':[x for x in amenities if x not in old['amenities']]})
    common=['wifi','restaurant','room_service','Giữ hành lý','Lễ tân 24 giờ','Dọn phòng hằng ngày']
    if hotel['id']!=1:common+=['parking'] # The sourced MEANDER listing explicitly says no onsite parking.
    if median is not None and median>=1200000:common+=['breakfast','bar','Dịch vụ giặt là','airport_shuttle']
    if median is not None and median>=2000000:common+=['gym','spa','pool','Phòng họp']
    if median is not None and median>=4000000:common+=['Dịch vụ hỗ trợ khách','Dịch vụ giữ trẻ']
    assigned=[x['amenity'] for x in hotel['hotel_amenities']]
    preferred={norm(x['amenity']):x['amenity'] for x in audit['amenity_catalog']}
    common=[preferred.get(norm(x),x) for x in common]
    add=[x for x in merge(assigned,common) if x not in assigned]
    hotels.append({'id':hotel['id'],'name':hotel['name'],'status':hotel['status'],'existing_amenity_ids':[x['id'] for x in hotel['hotel_amenities']],
      'amenities_added':add,'tier_basis':'Existing project median room price; DEMO amenities, not verified real-world facilities'})
manifest={'version':1,'checked_on':'2026-11-01','data_kind':'user_authorized_demo_capacity_amenities_and_activation',
 'authorization':'User permits approximate/fictitious capacities and amenities for the class demonstration, but requires real prices.',
 'source_uri':'project://docs/itinerary-approved-requirements.md#demo-catalog-20261031',
 'price_policy':'Preserve existing prices and all date/season rates. Never fabricate new prices; a price requires exact property/category/date/conditions. No new inventory.',
 'records':catalog,'hotels':hotels,'verified_rates':[]}
(ROOT/'database/data/demo-room-catalog-2026-10-31.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n',encoding='utf8')
print(json.dumps({'types':len(catalog),'capacities_filled':sum(x['before']['capacity'] is None for x in catalog),'demo_capacities':sum(x['demo_capacity'] for x in catalog),'verified_capacities':sum(x['capacity_source'] is not None for x in catalog),'types_activated':sum(x['before']['status']!='active' for x in catalog),'room_amenities_added':sum(len(x['amenities_added']) for x in catalog),'hotel_amenities_added':sum(len(h['amenities_added']) for h in hotels),'prices_fabricated':0,'physical_rooms_added':0}))
