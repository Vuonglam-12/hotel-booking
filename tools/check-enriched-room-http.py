"""Read-only smoke checks on the running local customer API; no account or booking writes."""
import concurrent.futures
import json
import pathlib
import urllib.request

ROOT=pathlib.Path(__file__).resolve().parents[1]
WORK=ROOT/'storage/app/private/room-type-enrichment-20261031'
before=json.loads((WORK/'before.json').read_text(encoding='utf-8'))
old_ids={t['room_type_id'] for h in before['hotels'] for t in h['types']}

def check(h):
    url='http://localhost:8000/api/hotels/'+str(h['id'])+'/rooms?check_in=2026-11-15&check_out=2026-11-17'
    with urllib.request.urlopen(url,timeout=30) as response:
        data=json.load(response)
        assert response.status==200 and isinstance(data,list)
        assert all(r['hotel_id']==h['id'] and r['room_type_id'] in old_ids for r in data)
        assert all(r['price'] is not None and r['room_type']['base_price'] is not None for r in data)
        return {'hotel_id':h['id'],'http_status':200,'physical_rows':len(data),'new_unpriced_types_exposed':False}

with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool: result=list(pool.map(check,before['hotels']))
(WORK/'http-report.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
print(json.dumps({'hotels_checked':len(result),'http_200':len(result),'new_unpriced_types_exposed':False}))
