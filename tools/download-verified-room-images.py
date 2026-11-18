"""Download original hotel images selected from exact category evidence; never generate images."""
import concurrent.futures
import hashlib
import json
import pathlib
import subprocess

ROOT=pathlib.Path(__file__).resolve().parents[1]
WORK=ROOT/'storage/app/private/room-type-enrichment-20261031'
ENTRIES=json.loads((WORK/'image-candidates.json').read_text(encoding='utf-8'))
previous={x['key']:x for x in json.loads((WORK/'downloaded-images.json').read_text(encoding='utf-8'))} if (WORK/'downloaded-images.json').exists() else {}

def download(item):
    # Windows may resolve the junction into a different case spelling of the same root.
    relative=pathlib.PurePosixPath(item['local_path'])
    if not str(relative).startswith('storage/accommodations/verified-room-types/20261031/') or '..' in relative.parts or relative.suffix not in ['.jpg','.webp']: raise ValueError('Unexpected image target')
    target=ROOT/'storage/app/public'/pathlib.Path(*relative.parts[1:])
    target.parent.mkdir(parents=True,exist_ok=True)
    if target.exists() and previous.get(item['key'],{}).get('source_url')!=item['source_url']:
        target.unlink() # Only this task's exact new image; no existing database image paths.
    if not target.exists():
        run=subprocess.run(['curl.exe','--silent','--show-error','--fail','--location','--max-time','25',
            '--max-redirs','4','--user-agent','Mozilla/5.0','--output',str(target),item['source_url']],capture_output=True)
        if run.returncode: return {**item,'error':'Source image download failed'}
    size=target.stat().st_size
    # Keep only original JPEGs; validate decoder and dimensions with PHP GD below.
    header=target.read_bytes()[:12]
    valid=header[:3]==b'\xff\xd8\xff' if relative.suffix=='.jpg' else header[:4]==b'RIFF' and header[8:12]==b'WEBP'
    if not valid or size>20*1024*1024:
        target.unlink()
        return {**item,'error':'Not an original JPEG within size limit'}
    code='$s=@getimagesize($argv[1]); if(!$s||$s[0]<400||$s[1]<200)exit(2); echo json_encode([$s[0],$s[1]]);'
    check=subprocess.run(['php','-r',code,str(target)],capture_output=True,text=True)
    if check.returncode:
        target.unlink()
        return {**item,'error':'Invalid or small image'}
    return {**item,'sha256':hashlib.sha256(target.read_bytes()).hexdigest(),'bytes':size,'dimensions':json.loads(check.stdout)}

with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
    results=list(pool.map(download,ENTRIES))
(WORK/'downloaded-images.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'verified_originals':sum('sha256' in x for x in results),'failed':sum('error' in x for x in results),
    'bytes':sum(x.get('bytes',0) for x in results)}))
