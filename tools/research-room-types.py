"""Cache public hotel evidence. No credentials, booking engines, or Sheet writes."""
import concurrent.futures
import hashlib
import json
import pathlib
import re
import subprocess
import sys
from html.parser import HTMLParser
from urllib.parse import urljoin

ROOT = pathlib.Path(__file__).resolve().parents[1]
CACHE = ROOT / 'storage/app/private/room-type-enrichment-20261031/sources'

class Page(HTMLParser):
    def __init__(self, url):
        super().__init__(convert_charrefs=True)
        self.url, self.text, self.links, self.images = url, [], [], []
        self.skip = 0
        self.anchor = None
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag in ('script', 'style', 'noscript', 'svg'): self.skip += 1
        if tag in ('p','div','li','h1','h2','h3','h4','br','tr'): self.text.append('\n')
        if tag == 'a' and a.get('href'): self.anchor = [urljoin(self.url, a['href']), []]
        if tag == 'img':
            src = a.get('data-src') or a.get('data-lazy-src') or a.get('src')
            if src and not src.startswith('data:'): self.images.append({'url':urljoin(self.url,src),'alt':a.get('alt','')})
        if tag == 'meta' and a.get('property') == 'og:image': self.images.append({'url':urljoin(self.url,a.get('content','')),'alt':'og:image'})
    def handle_endtag(self, tag):
        if tag in ('script','style','noscript','svg'): self.skip = max(0,self.skip-1)
        if tag == 'a' and self.anchor:
            self.links.append({'url':self.anchor[0],'label':' '.join(self.anchor[1])})
            self.anchor = None
        if tag in ('p','div','li','h1','h2','h3','h4','tr'): self.text.append('\n')
    def handle_data(self, data):
        if not self.skip:
            self.text.append(data)
            if self.anchor: self.anchor[1].append(data.strip())

def fetch(item):
    key, url = item
    CACHE.mkdir(parents=True, exist_ok=True)
    target = CACHE / (key+'.html')
    run = subprocess.run(['curl.exe','--silent','--show-error','--location','--fail','--max-time','35','--max-redirs','5',
        '--user-agent','Mozilla/5.0','--output',str(target),url],capture_output=True)
    if run.returncode:
        return {'key':key,'url':url,'error':'HTTP or network failure','curl_exit':run.returncode}
    raw = target.read_bytes()
    parser = Page(url)
    parser.feed(raw.decode('utf-8',errors='replace'))
    lines = [re.sub(r'\s+',' ',x).strip() for x in ''.join(parser.text).splitlines()]
    lines = [x for x in lines if x]
    result = {'key':key,'url':url,'checked_on':'2026-10-31','sha256':hashlib.sha256(raw).hexdigest(),
        'lines':lines,'links':list({x['url']:x for x in parser.links}.values()),
        'images':list({x['url']:x for x in parser.images}.values())}
    (CACHE/(key+'.json')).write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
    return {'key':key,'lines':len(lines),'images':len(result['images']),'sha256':result['sha256']}

if __name__ == '__main__':
    entries = json.loads(pathlib.Path(sys.argv[1]).read_text(encoding='utf-8-sig'))
    with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
        for result in pool.map(fetch, entries.items()): print(json.dumps(result,ensure_ascii=False),flush=True)
