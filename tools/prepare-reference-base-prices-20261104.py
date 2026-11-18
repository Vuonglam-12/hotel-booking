"""Match explicitly reviewed room aliases to real public quotes for project base prices."""
import collections,decimal,hashlib,json,pathlib,re
root=pathlib.Path(__file__).resolve().parents[1];work=root/'storage/app/private/room-admin-update-20261102'
audit=json.loads((work/'audit-before.json').read_text(encoding='utf8'));offers=json.loads((work/'google-price-candidates.json').read_text(encoding='utf8'))
# Key is the existing room_type ID. Never match solely on price, bed count, or hotel minimum.
aliases={
66:['Deluxe Double Bed Private'],86:['Penthouse Apartment','Penthouse'],
77:['Deluxe Triple Room'],79:['La Siesta Suite'],80:['Duplex Suite'],81:['Family Suite'],82:['Club Duplex Suite'],
87:['CENTURY CITY DELUXE DOUBLE','CENTURY CITY DELUXE TWIN'],88:['CENTURY POOL DELUXE DOUBLE OR TWIN'],89:['CENTURY MAJESTIC SUITE'],90:['CENTURY PRESIDENTIAL SUITE'],
95:['Deluxe Connecting Room'],96:['Executive Connecting Room'],
100:['Luxury Suite King City View or River View'],102:['Grand Executive Suite King City View or Balcony View'],103:['Grand Suite City View or Balcony View'],
106:['2 twin club panoramic ocean view'],107:['1 king terrace suite ocean view'],108:['1 king club suite panoramic ocean view'],111:['1 bedroom seaside villa on the rocks'],112:['1 bedroom spa lagoon villa'],
114:['Deluxe Queen Ocean View'],115:['Family Connecting Room'],116:['JUNIOR SUITE, 1 King-Size Bed, Private Terrace'],117:['Junior Family Suite with 1 Kingsize bed and 1 Bunk bed, Private balcony'],118:['FAMILY SUITE'],119:['PENTHOUSE SUITE, 1 King-Size Bed, High Floor'],120:['COTTAGE, 1 Bedroom, 1 King-Size Bed, Private Garden'],
122:['Superior River View Double Room'],
132:['Deluxe Twin Room with Garden View'],134:['Deluxe Twin Room with Private Garden View','Deluxe Twin Private Garden View'],135:['Deluxe King Partial Sea View With Balcony'],139:['Premium King Sea View'],
142:['Deluxe Garden View King Bed'],
151:['DELUXE OCEAN KING WITH BALCONY'],152:['GRAND DELUXE OCEAN KING WITH BALCONY'],153:['PREMIUM DELUXE OCEAN KING | INDOOR JACUZZI'],154:['JUNIOR SUITE OCEAN CORNER|DUAL BALCONIES| JACUZZIS'],155:['EXECUTIVE SUITE OCEAN TWIN|PANORAMIC VIEW|JACUZZIS'],
164:['Thu Bon River Suite'],165:['Anantara Two Bedroom River Suite'],169:['Royal Superior Room'],170:['Royal Luxury Lake or City View Room'],
181:['Family Superior'],184:['Family Villa'],185:['Tropical Villa'],186:['Pool Access Villa Double – Adults Only'],187:['Plunge Pool Villa'],
190:['Premier Room Ocean View'],191:['Three Bedroom Premier Suites'],192:['Three Bedroom Executive Suites'],193:['Three Bedroom Deluxe Suites'],
297:['Villa 2 Bedroom'],
205:['Pacific Club King','Pacific Club Room'],206:['Pacific Club Lakeview King'],207:['Studio Suite'],208:['Studio Balcony Suite'],
212:['Executive'],213:['Premium Executive'],214:['Presidential Suite'],
215:['VILLA SUITE'],218:['ANA MANDARA SUITE'],219:['Five-Bedroom Villa'],220:['Four-Bedroom Villa'],221:['Three-Bedroom Villa'],
227:['2 TWIN BEDS OCEAN VIEW'],229:['1 BEDROOM RESIDENCE'],230:['1 BEDROOM OCEAN VIEW RESIDENCE'],231:['REGENCY OCEAN VIEW SUITE'],232:['REGENCY SUITE'],233:['2 BEDROOM RESIDENCE'],
234:['Deluxe Room, city view'],235:['Superior Room, Balcony, Han River View/ Bay View'],236:['Deluxe Room, Balcony, Dragon Bridge View'],238:['Corner Suite, panoramic Han River view, bathtub, Premier Lounge Access'],239:['Studio apartment, balcony, Han River views , fully equipped kitchens'],
241:['Deluxe Double or Twin Room'],242:['Family Connecting Room'],243:['Junior Suite'],244:['Executive Suite'],245:['Presidential Suite'],
250:['Premium Double Room without Views','Premium Double Without View'],251:['Premium City View Twin Room','Premium City View Twin'],252:['Premium Garden View Double'],253:['Premium City View Double Room'],
260:['Deluxe Twin Room Garden View with Children themed room'],265:['Deluxe Villa Garden View'],266:['Premier Deluxe King Villa Garden View'],
268:['1 Bedroom Junior Suite','Junior Suite, 1 Bedroom'],271:['1 Bedroom Presidential Suite','Presidential Suite, 1 Bedroom'],272:['Guest room, 2 Twin, Ocean view','Room, 2 Twin Beds, Ocean View'],273:['Larger Guest room, 1 King, Ocean view'],
279:['1 king classic ocean view balcony'],280:['1 king junior suite ocean view balcony'],281:['2 bedroom family suite'],282:['1 bedroom penthouse club lounge access ocean view'],
286:['1 king beach pool villa'],287:['1 king terrace pool villa'],288:['1 king garden pool suite','2 single garden pool suite'],290:['2 bedroom lagoon pool villa'],291:['2 bedroom sky pool villa'],292:['2 bedroom terrace pool villa'],
}
types={row['room_type_id']:row for row in audit['types'] if row['base_price'] is None};records=[];unmatched=[]
for type_id,names in aliases.items():
    if type_id not in types:raise ValueError('Only fill an existing NULL base price')
    room=types[type_id];matches=[]
    for offer in offers:
        if offer['hotel_id']!=room['hotel_id']:continue
        # A complete reviewed name must end before its plan/bed/guest annotations.
        found=next((name for name in names if re.search(re.escape(name)+r'(?=\s*-\s|\d|Suite|\s*Free cancellation|\s*breakfast|\s*Balcony|\s*$)',offer['offer_text'],re.I)),None)
        if not found:continue
        if type_id in [234,235,236] and '2 single beds' not in offer['offer_text'].lower():continue
        if type_id==227 and 'WITH CLUB ACCESS' in offer['offer_text']:continue
        if type_id==273 and 'Club lounge access' in offer['offer_text']:continue
        matches.append((offer,found))
    if not matches:unmatched.append({'hotel_id':room['hotel_id'],'room_type_id':type_id,'name':room['name']});continue
    offer,found=min(matches,key=lambda pair:(pair[0]['check_in']!='2026-11-15',decimal.Decimal(pair[0]['nightly_reference_vnd']),pair[0]['evidence_path']))
    evidence=root/offer['evidence_path'];snapshot=json.loads(evidence.read_text(encoding='utf8'))
    # Date fields from the rendered form must match the requested month/day; year is in the URL and request.
    months={'11':'Nov','12':'Dec'}
    for key,label in [('check_in','Check-in'),('check_out','Check-out')]:
        date=offer[key];expected=months[date[5:7]]+' '+str(int(date[8:10]));selected=[i['value'] for i in snapshot['inputs'] if i.get('label')==label]
        if not selected or not all(expected in value for value in selected):raise ValueError('Selected dates do not match source: '+offer['evidence_path'])
    price=(decimal.Decimal(offer['stay_total_vnd'])/offer['nights']).quantize(decimal.Decimal('.01'))
    records.append({'hotel_id':room['hotel_id'],'hotel_room_type_id':room['id'],'room_type_id':type_id,'name':room['name'],'base_price_before':None,'base_price_vnd':str(price),'currency':'VND','source_room_name':found,'source_offer_text':offer['offer_text'],'source_url':offer['source_url'],'checked_on':offer['checked_on'],'check_in':offer['check_in'],'check_out':offer['check_out'],'searched_adults':2,'rooms':1,'stay_total_vnd':offer['stay_total_vnd'],'nights':offer['nights'],'source_displayed_nightly_total_vnd':offer['nightly_reference_vnd'],'calculation':'stay_total / nights; user-authorized project base price','evidence_path':offer['evidence_path'],'evidence_sha256':hashlib.sha256(evidence.read_bytes()).hexdigest()})
# Genuine two-night Expedia totals already archived and validated in the previous task.
for quote in json.loads((root/'database/data/verified-room-rate-references-2026-11-01.json').read_text(encoding='utf8'))['records']:
    type_id=quote['room_type_id']
    if type_id not in types:continue
    room=types[type_id];records.append({'hotel_id':room['hotel_id'],'hotel_room_type_id':room['id'],'room_type_id':type_id,'name':room['name'],'base_price_before':None,'base_price_vnd':quote['nightly_average_vnd'],'currency':'VND','source_room_name':quote['source_room_name'],'source_offer_text':quote['conditions_excerpt'],'source_url':quote['source_url'],'checked_on':quote['checked_on'],'check_in':quote['check_in'],'check_out':quote['check_out'],'searched_adults':2,'rooms':1,'stay_total_vnd':quote['stay_total_vnd'],'nights':quote['nights'],'source_displayed_nightly_total_vnd':quote['nightly_average_vnd'],'calculation':'stay_total / nights; user-authorized project base price','evidence_path':quote['evidence_path'],'evidence_sha256':quote['evidence_sha256']})
assert len({row['hotel_room_type_id'] for row in records})==len(records)
manifest={'prepared_on':'2026-11-04','source_kind':'verified_reference_quotes_as_user_authorized_project_base_prices','authorization_uri':'project://docs/itinerary-approved-requirements.md#reference-base-prices','records':records}
target=root/'database/data/reference-base-prices-2026-11-04.json'
if (work/'base-prices-apply-report.json').exists():raise RuntimeError('Preserve the applied price manifest')
target.write_text(json.dumps(manifest,ensure_ascii=False,indent=2),encoding='utf8')
(work/'unmatched-price-aliases.json').write_text(json.dumps(unmatched,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps({'price_records':len(records),'hotels':len({r['hotel_id'] for r in records}),'still_missing':len(types)-len(records),'aliases_without_quote':unmatched},ensure_ascii=True))
