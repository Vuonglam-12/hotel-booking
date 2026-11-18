"""Download selected real room photos. Decode originals; retain URL/date and content hashes."""
import concurrent.futures,hashlib,json,pathlib,subprocess
ROOT=pathlib.Path(__file__).resolve().parents[1];WORK=ROOT/'storage/app/private/room-type-photo-completion-20261031'
entries=json.loads((WORK/'candidates.json').read_text(encoding='utf8'))
old={r['id']:r for r in json.loads((WORK/'downloaded.json').read_text(encoding='utf8'))} if (WORK/'downloaded.json').exists() else {}
def fetch(item):
    previous=old.get(item['id'],{})
    if previous.get('source_url')==item['source_url'] and previous.get('sha256'):
        target=ROOT/'public'/previous['local_path']
        if target.is_file() and hashlib.sha256(target.read_bytes()).hexdigest()==previous['sha256']:return previous
    temp=WORK/(str(item['id'])+'.download')
    run=subprocess.run(['curl.exe','--silent','--show-error','--fail','--location','--max-time','30','--max-redirs','4',
       *([] if 'fourseasons.com/' in item['source_url'] else ['--user-agent','Mozilla/5.0']),'--output',str(temp),item['source_url']],capture_output=True)
    if run.returncode:return {**item,'error':'Download failed: '+str(run.returncode)}
    data=temp.read_bytes();sig=data[:12]
    ext='jpg' if sig[:3]==b'\xff\xd8\xff' else 'png' if sig[:8]==b'\x89PNG\r\n\x1a\n' else 'webp' if sig[:4]==b'RIFF' and sig[8:12]==b'WEBP' else None
    if ext is None or len(data)>20*1024*1024:return {**item,'error':'Invalid format/size'}
    check=subprocess.run(['php','-r','$s=@getimagesize($argv[1]);if(!$s||$s[0]<400||$s[1]<200)exit(2);echo json_encode([$s[0],$s[1]]);',str(temp)],capture_output=True,text=True)
    if check.returncode:return {**item,'error':'Image cannot decode or is too small'}
    local='storage/accommodations/verified-room-types/photos-20261031/'+str(item['id'])+'-'+item['key']+'.'+ext
    target=ROOT/'storage/app/public'/pathlib.Path(*pathlib.PurePosixPath(local).parts[1:]);target.parent.mkdir(parents=True,exist_ok=True)
    if target.exists() and target.read_bytes()!=data:raise ValueError('Refuse overwrite of a different existing photo: '+local)
    target.write_bytes(data)
    return {**item,'local_path':local,'sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data),'dimensions':json.loads(check.stdout)}
with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:results=list(pool.map(fetch,entries))
(WORK/'downloaded.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps({'verified':sum('sha256' in x for x in results),'failed':[{ 'id':x['id'],'error':x['error']} for x in results if 'error' in x]}))
