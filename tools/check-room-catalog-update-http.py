"""Check actual customer quotes, date bounds and zero-inventory rejection; no bookings/writes."""
import concurrent.futures,json,pathlib,urllib.request,urllib.error
from decimal import Decimal
ROOT=pathlib.Path(__file__).resolve().parents[1];WORK=ROOT/'storage/app/private/room-catalog-update-20261031'
quotes=json.loads((ROOT/'database/data/verified-room-rate-references-2026-11-01.json').read_text(encoding='utf8'))['records']
audit=json.loads((ROOT/'storage/app/private/room-catalog-readiness-20261031/audit.json').read_text(encoding='utf8'))
def request(hotel,room,start,end):
    url=f'http://localhost:8000/api/hotels/{hotel}/room-types/{room}/quote?check_in={start}&check_out={end}&quantity=1'
    try:
        with urllib.request.urlopen(urllib.request.Request(url,headers={'Accept':'application/json'}),timeout=40) as response:return response.status,json.load(response)
    except urllib.error.HTTPError as e:return e.code,None
def check(row):
    old=next(t for h in audit['hotels'] if h['id']==row['hotel_id'] for t in h['types'] if t['room_type_id']==row['room_type_id'])
    status,data=request(row['hotel_id'],row['room_type_id'],row['check_in'],row['check_out'])
    if not old['physical_rooms']:
        assert status==404
        return {'hotel_room_type_id':row['hotel_room_type_id'],'no_inventory_quote_rejected':True,'http_status':404}
    assert status==200
    assert Decimal(str(data['total_price']))==Decimal(row['stay_total_vnd'])
    outside,other=request(row['hotel_id'],row['room_type_id'],'2026-11-17','2026-11-18')
    assert outside==200 and Decimal(str(other['total_price']))==Decimal(old['base_price'])
    return {'hotel_room_type_id':row['hotel_room_type_id'],'http_status':200,'stay_total_matches_source':True,'reference_not_extrapolated_outside_period':True}
with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:result=list(pool.map(check,quotes))
(WORK/'http-quote-report.json').write_text(json.dumps(result,indent=2),encoding='utf8')
print(json.dumps({'quotes_checked':len(result),'source_total_matches':sum(r.get('stay_total_matches_source',False) for r in result),'zero_inventory_404':sum(r.get('no_inventory_quote_rejected',False) for r in result),'outside_period_preserved':sum(r.get('reference_not_extrapolated_outside_period',False) for r in result)}))
