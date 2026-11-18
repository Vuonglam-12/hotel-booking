"""Read-only HTTP verification of each newly attached photo, including its actual content hash."""
import concurrent.futures,hashlib,json,pathlib,urllib.request
ROOT=pathlib.Path(__file__).resolve().parents[1];WORK=ROOT/'storage/app/private/room-type-photo-completion-20261031'
records=json.loads((ROOT/'database/data/verified-room-type-photos-2026-10-31.json').read_text(encoding='utf8'))['records']
def check(row):
    url='http://localhost:8000/'+row['local_path']
    with urllib.request.urlopen(url,timeout=35) as response:
        data=response.read();assert response.status==200 and response.headers.get('Content-Type','').startswith('image/')
        assert hashlib.sha256(data).hexdigest()==row['sha256']
        return {'id':row['id'],'http_status':200,'content_hash_matches':True,'local_path':row['local_path']}
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:result=list(pool.map(check,records))
(WORK/'http-photo-report.json').write_text(json.dumps(result,indent=2),encoding='utf8')
print(json.dumps({'photos_checked':len(result),'http_200':len(result),'all_content_hashes_match':True}))
