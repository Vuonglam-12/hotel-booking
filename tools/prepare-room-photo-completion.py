"""Select only exact room galleries/captions already verified in public hotel evidence."""
import json,pathlib,re,html
from urllib.parse import urlsplit,urlunsplit,quote
ROOT=pathlib.Path(__file__).resolve().parents[1]
WORK=ROOT/'storage/app/private/room-type-photo-completion-20261031'
CACHE=ROOT/'storage/app/private/room-type-enrichment-20261031/sources'
MISSING=json.loads((WORK/'missing.json').read_text(encoding='utf8'))
out={}
def add(row,url,note,page=None):
    parts=urlsplit(html.unescape(url));url=urlunsplit((parts.scheme,parts.netloc,quote(parts.path,safe='/%:@-_.~'),quote(parts.query,safe='=&%:+,;/-_.~'),''))
    out[row['id']]={'id':row['id'],'hotel_id':row['hotel_id'],'room_type_id':row['room_type_id'],'name':row['name'],'key':row['key'],
      'page_url':page or row['record']['source_url'],'source_url':url,'checked_on':'2026-10-31','evidence_note':note,'source_key':row['record']['source_key']}
for row in MISSING:
    h=row['hotel_id']; imgs=row['cached'].get('images',[]);pick=None
    if h==14:pick=next((i for i in imgs if re.search(r'\.(?:jpg|jpeg)$',i['url'],re.I)),None)
    elif h==21:pick=next((i for i in imgs if '/'+row['record']['source_key'].title()+'/' in i['url']),None) # Case differs below: compare exact slug folder.
    if h==21:pick=next((i for i in imgs if '/'+row['record']['source_key'].lower()+'/' in i['url'].lower()),None)
    elif h==48:pick=next((i for i in imgs if i['url'].endswith('/Room-606.png')),None)
    elif h==49:pick=next((i for i in imgs if i['alt']=='og:image'),None)
    elif h==57:
        markers={356:'/21.png',357:'/31.png',358:'/AmianaNhaTrang_DeluxeTwin03-1.jpg',359:'/AmianaNhaTrang_DeluxeKing01-1-1.jpg'}
        pick=next((i for i in imgs if i['url'].endswith(markers[row['id']])),None)
    elif h==58:
        caption={364:'Presidential Suite - Nha Trang Hotel',365:'Deluxe Twin Room - Bedroom',366:'Premium Guest Room - Bedroom'}[row['id']]
        pick=next((i for i in imgs if i['alt']==caption),None)
        if pick:pick={**pick,'url':pick['url'].replace('wid=375','wid=1200').replace('downsize=375px','downsize=1200px')}
    elif h==43:
        page=json.loads((CACHE/('43-appartment.json' if row['id']>=284 else '43.json')).read_text(encoding='utf8'))
        slug=row['record']['source_key'];pick=next((i for i in page['images'] if slug in i['url']),None)
        if pick:add(row,pick['url'],'Official room listing card labelled with this exact category.',page['url']);continue
    if pick:add(row,pick['url'],'Photograph in the exact official room category gallery; reviewed visually before import.')
for link in json.loads((WORK/'resolved-web-links.json').read_text(encoding='utf8')):
    row=next(x for x in MISSING if x['id']==link['id'])
    url=link.get('url') or re.search(r'Failed to fetch (https?://.*?): (?:Internal|\()',link['text'])[1]
    add(row,url,'Official room listing: linked photograph immediately precedes this room heading; source reference '+link['ref']+'.')
for row in MISSING:
    h=row['hotel_id']
    if h==50:
        page=json.loads((CACHE/('50-all.json' if row['id']==317 else '50-page2.json')).read_text(encoding='utf8'))
        normalize=lambda s:re.sub(r'\s+',' ',s).strip().lower()
        photo=next(i for i in page['images'] if normalize(i['alt'])==normalize(row['name']))
        add(row,photo['url'],'Official listing image alt text equals the room category name.',page['url'])
    elif h in [47,53]:
        raw=(CACHE/f'{h}.html').read_text(encoding='utf8')
        slug={307:'presidential-suite',334:'deluxe',335:'family-connecting'}[row['id']]
        chunks=re.split(r'<div[^>]*class="accordion-item[^>]*>',raw)[1:]
        chunk=next(c for c in chunks if 'href="#accordion-item-'+slug+'"' in c)
        suffix={307:'/IMG_2036-scaled.jpg',334:'/t-1.jpg',335:'/tt-1.jpg'}[row['id']]
        url=next(u for u in re.findall(r'<img[^>]*src="([^"]+)"',chunk) if u.endswith(suffix))
        add(row,url,'Photograph within the specifically labelled official room accordion.',f'https://silkpathhotel.com/'+('accommondationhanoi/' if h==47 else 'slphueaccommonation/')+'#accordion-item-'+slug)
    elif h==52:
        raw=(CACHE/'52.html').read_text(encoding='utf8');code={327:'TWA',328:'TWBRV',329:'TWB',330:'SKA',331:'SKB',332:'AWC',333:'A1C'}[row['id']]
        chunk=next(c for c in re.split(r'<div class="card card--default" data-code="',raw)[1:] if c.startswith(code+'"'))
        url=re.search(r'<img[^>]*src="([^"]+)"',chunk)[1]
        add(row,url,'Accor hotel 8287 room card '+code+' contains this exact category photograph.','https://all.accor.com/hotel/8287/index.en.shtml')
    elif h==9:
        raw=(CACHE/(row['key']+'.html')).read_text(encoding='utf8')
        suffix='Resort-Classic-Room-Twin-low-res-Web.jpg' if row['id']==197 else 'Resort-Classic-Panoramic-bedroom-web.jpg'
        url=next(u for u in re.findall(r'https?[^\s\"<>\)]+?\.(?:jpg|webp)',raw) if u.endswith('/'+suffix))
        add(row,url,'Photograph from the exact room page gallery, including its original background image URL.')
if (WORK/'extra-candidates.json').exists():
    for extra in json.loads((WORK/'extra-candidates.json').read_text(encoding='utf8')):
        row=next(x for x in MISSING if x['id']==extra['id']);add(row,extra['source_url'],extra['evidence_note'],extra['page_url'])
for path in WORK.glob('resolved-extra-*.json'):
    for extra in json.loads(path.read_text(encoding='utf8')):
        if not extra.get('source_url'):continue
        row=next(x for x in MISSING if x['id']==extra['id']);add(row,extra['source_url'],extra['evidence_note'],extra['page_url'])
trip3=json.loads((WORK/'trip-mamay-room-map.json').read_text(encoding='utf8'))
for room_id,name in {170:'Deluxe Triple Room',171:'Club Junior Suite',172:'La Siesta Suite',173:'Duplex Suite',174:'Family Suite',175:'Club Duplex Suite'}.items():
    row=next(x for x in MISSING if x['id']==room_id)
    obj=next(v for v in trip3.values() if v['name']==name)
    add(row,obj['pictureInfo'][0]['url'],'Trip.com public physicRoomMap category '+str(obj['id'])+' labelled '+name+'.','https://my.trip.com/hotels/hanoi-hotel-detail-2083705/la-siesta-classic-ma-may/')
trip_matches={5:{180:6154394,181:6154367,182:18387553},6:{184:491137780,185:490810302,186:491137875,187:518208874},
  7:{188:443811285,189:443538263,190:386208736,191:386208739},8:{192:250709944,193:21121668,194:21121832,195:250713415,196:4255619},
  44:{288:577858594,289:574695106,290:574695164,291:574695311,292:574695259,293:574695165,294:574695260,295:574695489}}
trip_urls=json.loads((WORK/'fetch-trip.json').read_text(encoding='utf8'))
for h,matched in trip_matches.items():
    rooms=json.loads((WORK/f'trip-{h}-room-map.json').read_text(encoding='utf8'))
    for category,trip_id in matched.items():
        row=next(x for x in MISSING if x['id']==category);obj=rooms[str(trip_id)]
        add(row,obj['pictureInfo'][0]['url'],'Trip.com public physicRoomMap '+str(trip_id)+' labelled '+obj['name']+'; matched to the existing category at the same hotel.',trip_urls[f'photo-trip-{h}'])
(WORK/'candidates.json').write_text(json.dumps(list(out.values()),ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps({'selected':len(out),'remaining':len(MISSING)-len(out)}))
