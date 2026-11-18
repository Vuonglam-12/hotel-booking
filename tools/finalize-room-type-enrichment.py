"""Build the import manifest from the reviewed plan and cached public evidence."""
import collections
import hashlib
import html
import json
import pathlib
import re
from urllib.parse import urljoin, urlparse

ROOT = pathlib.Path(__file__).resolve().parents[1]
WORK = ROOT/'storage/app/private/room-type-enrichment-20261031'
CACHE = WORK/'sources'
PLAN = json.loads((WORK/'research-plan.json').read_text(encoding='utf-8'))
CACHE_PAGES=[json.loads(p.read_text(encoding='utf-8')) for p in CACHE.glob('*.json')]

def read(key):
    p=CACHE/(key+'.json')
    return json.loads(p.read_text(encoding='utf-8')) if p.exists() else None

def between(lines,start,end=None):
    try: i=next(i for i,x in enumerate(lines) if x==start)
    except StopIteration: return []
    j=next((j for j in range(i+1,len(lines)) if end and lines[j]==end),len(lines))
    return lines[i+1:j]

def area(text):
    found = re.search(r'(\d+(?:\.\d+)?)\s*(?:[-–]\s*(\d+(?:\.\d+)?))?\s*(?:sqm|sq\s*m\b|m²|m2|square metres|square meters)',text,re.I)
    return (float(found[1]),float(found[2] or found[1])) if found else (None,None)

def after(lines,label):
    block=between(lines,label)
    return block[0] if block else None

def setarea(row,text):
    lo,hi=area(text)
    if lo is not None: row['area_min_m2'],row['area_max_m2']=lo,hi

def setbed(row,text):
    if not text: return
    text=text.strip('• ').replace('Luxury ','').replace('luxury ','')
    if len(text)<=50: row['bed_type']=text
    else: row['bed_details']=text

def amenities(text):
    # Extract only amenities explicitly described in this room's section.
    rules=[('Wi-Fi',r'\bwi[ -]?fi\b'),('Điều hòa',r'air.condition|climate.control'),('TV',r'\bTV\b|television'),
        ('Tủ lạnh',r'refrigerator|mini.?fridge'),('Minibar',r'mini.?bar'),('Két an toàn',r'\bsafe\b|safety box'),
        ('Máy sấy tóc',r'hair.?dry'),('Bồn tắm',r'bathtub|bath.?tub|soaking tub|bồn tắm'),
        ('Vòi sen',r'shower|vòi sen'),('Ban công riêng',r'private balcon'),('Sân hiên riêng',r'private terrace'),
        ('Bếp riêng',r'fully.equipped kitchen|private kitchen'),('Hồ bơi riêng',r'private.*?pool'),
        ('Bàn làm việc',r'work.*?desk|working desk|work station'),('Máy pha cà phê',r'coffee machine|coffee maker')]
    return [name for name,pattern in rules if re.search(pattern,text,re.I)]

def rawimages(key,page):
    candidates=list(page['images'])
    path=CACHE/(key+'.html')
    if path.exists():
        raw=path.read_text(encoding='utf-8',errors='replace')
        for value in re.findall(r'''(?:url\([\s'"]*|data-bg=["'])([^\s"'<>\)]+)''',raw):
            if re.search(r'\.(?:jpe?g)(?:\?|$)',value,re.I): candidates.append({'url':urljoin(page['url'],html.unescape(value)),'alt':'room-page background'})
    return list({x['url']:x for x in candidates}.values())

pages={}
for row in PLAN:
    key=str(row['hotel_id'])+'-'+row['source_key']
    page=read(key)
    if page and len(page['lines'])>10 and not any('Error 404' in x for x in page['lines']): pages[key]=page
frequency=collections.Counter()
for key,page in pages.items():
    for img in rawimages(key,page): frequency[(key.split('-')[0],img['url'])]+=1

manifest=[]
image_candidates=[]
for row in PLAN:
    h=row['hotel_id']; key=str(h)+'-'+row['source_key']
    row['issues']=[]
    row['evidence_sha256']=[]
    page=pages.get(key)
    lines=page['lines'] if page else []
    text=''
    if page:
        row['evidence_sha256'].append(page['sha256'])
        if h==1:
            own=between(lines,'Specifications','Explore More');text='\n'.join(own)
            setarea(row,after(own,'Room Size') or '')
            setbed(row,after(own,'Bed Type'))
        elif h==2:
            own=between(lines,'Overview','Other Rooms');text='\n'.join(own)
            setarea(row,text)
            setbed(row,next((x[5:] for x in own if x.startswith('Bed: ')),None))
        elif h==4:
            own=between(lines,'OverView','Room Rates');text='\n'.join(own)
            setarea(row,'\n'.join(x for x in own if x.startswith('Room size:')))
            setbed(row,next((x[5:] for x in own if x.startswith('Bed: ')),None))
            occ=re.search(r'Max Occupancy:\s*(\d+) people',text,re.I)
            if occ: row['capacity']=int(occ[1]);row['occupancy_text']=occ[0]
        elif h==9:
            own=between(lines,'SIZE','YOU MIGHT BE INTERESTED…');text='\n'.join(own)
            setarea(row,own[0] if own else '')
            setbed(row,after(own,'BEDS'))
            row['occupancy_text']=after(own,'OCCUPANCY')
            if row['occupancy_text']:
                if 'two adults and two children' in row['occupancy_text']: row['capacity']=4
                elif 'four adults and four children' in row['occupancy_text']: row['capacity']=8
        elif h==10:
            start=next((i for i,x in enumerate(lines) if x=='Room Features'),len(lines))
            own=lines[start:next((i for i in range(start+1,len(lines)) if lines[i]=='Discovery'),len(lines))]
            text='\n'.join(own);setarea(row,after(own,'Room size') or '')
            setbed(row,next((x for x in between(own,'Bedroom','Bathroom') if re.search('(?:king|queen|twin|double|single|bunk).*bed',x,re.I)),None))
        elif h==11:
            own=between(lines,'Room management software','Gallery') or between(lines,'Hotel management software','Gallery')
            text='\n'.join(own)
            measures=[area(x) for x in own if re.search('area|Diện tích',x,re.I) and area(x)[0] is not None]
            if len(set(measures))==1 and measures: row['area_min_m2'],row['area_max_m2']=measures[0]
            elif len(set(measures))>1: row['issues'].append('Nguồn tự mâu thuẫn diện tích; để trống diện tích.')
            bed=next((x.split(':',1)[1] for x in own if x.startswith('Cỡ giường:')),None)
            if bed and not re.search('King|Queen|Twin|Single|Double|bed',bed,re.I):
                row['issues'].append('Nhãn giường/hướng nhìn trên nguồn bị hoán đổi; để trống giường.')
            else: setbed(row,bed)
        elif h==12:
            own=between(lines,'Space','Other Room Categories');text='\n'.join(own)
            setarea(row,text)
            setbed(row,next((x for x in own if re.search(r'^\d+\s+(?:King|Single|Twin|Double)',x,re.I)),None))
            occ=next((x for x in own if re.search(r'^\d+\s+Adults?\s*&\s*\d+\s+Child',x,re.I)),None)
            if occ:
                row['capacity']=sum(map(int,re.findall(r'\d+',occ)));row['occupancy_text']=occ
        elif h==14:
            table={'suite-room-sea-view-double-twin':(35,35,'1 King hoặc 2 giường đơn'),
                'family-room':(65,65,'2 King'),'superior-room-window-no-window-twin-double':(25,25,'1 King hoặc 2 giường đơn'),
                'deluxe-room-triple-double-window-balcony':(28,30,None),'senior-room-with-balcony-double-twin':(35,35,'1 King hoặc 2 giường đơn')}
            lo,hi,bed=table[row['source_key']];row.update(area_min_m2=lo,area_max_m2=hi,bed_type=bed)
            text='\n'.join(lines[5:8])
        elif h==15:
            own=between(lines,'Rooms & Suites','Privacy Policy');text='\n'.join(own[:5])
        elif h==18:
            own=lines[lines.index('Book Now'):] if 'Book Now' in lines else lines
            # Room pages repeat navigation twice. The last title occurrence is the content heading.
            labels={'superior':'The Superior Rooms','luxury-exclusive':'The Luxury Exclusive Rooms','luxury-with-balcony':'The Luxury with Balcony Rooms',
                'suite':'The Suites','presidential-suite':'The Presidential Suite','royal-executive-suite':'The Royal Executive Suite',
                'royal-superior':'The Royal Superior','royal-suite':'The Royal Suite','royal-luxury':'The Royal Luxury'}
            label=labels[row['source_key']]
            positions=[i for i,x in enumerate(lines) if x==label]
            if positions:
                own=lines[positions[-1]+1:];text='\n'.join(own[:4]);setarea(row,text)
        elif h==20:
            text='\n'.join(lines[16:35])
        elif h==21:
            # Detail pages expose explicit SIZE/BED/SLEEP facts, not total resort inventory.
            text='\n'.join(between(lines,'ROOM DETAILS','OTHER ROOMS'))
            if not text:
                index=next((i for i,x in enumerate(lines) if re.search('sqm|m²|m2',x)),None)
                if index is not None: text='\n'.join(lines[max(0,index-2):index+22]);setarea(row,lines[index])
            bed=after(lines,'BED')
            if bed and re.search('1 king.*or 2 twin',bed,re.I): setbed(row,'1 King hoặc 2 giường đơn')
            elif bed: setbed(row,bed)
            occ=next((x.lstrip('• ') for x in lines if re.match(r'^[• ]*Maximum occupancy \d+',x,re.I)),None)
            if occ:
                row['occupancy_text']=occ
                alternatives=occ.split(' or ')
                totals=[sum(map(int,re.findall(r'(\d+) (?:adults?|children)',a))) for a in alternatives]
                if all(totals): row['capacity']=max(totals)
        elif h==46:
            index=next((i for i,x in enumerate(lines) if re.match(r'^\d+sqm\s*\(',x)),None)
            if index is not None:
                own=lines[index:index+30];text='\n'.join(own)
                setbed(row,next((x for x in own if re.search(r'^(?:A King|A Queen|A Choice|King|One King)',x)),None))
        elif h==48:
            own=between(lines,'Room Amenities','Gallery');text='\n'.join(own)
            setarea(row,next((x for x in own if x.startswith('Size:')), ''))
            setbed(row,after(own,'Bed types'));row['occupancy_text']=after(own,'Max occupancy')
            if row['occupancy_text'] and re.match(r'2 adults & 1 child',row['occupancy_text'],re.I): row['capacity']=3
        elif h==49:
            # Detail endpoints sometimes return the index page; never take another room's facts.
            text=''
        elif h==55:
            start=next((i for i,x in enumerate(lines) if x=='Other Rooms & Suites'),len(lines))
            # The first prose paragraph is this category; later descriptions belong to other rooms.
            paragraphs=[x for x in lines[:start] if len(x)>110 and not re.search('copyright|cookie|script',x,re.I)]
            text=paragraphs[0] if paragraphs else ''
            setarea(row,'\n'.join(lines[:start]))
            if re.search('queen or twin',text,re.I):
                row['issues'].append('Mô tả giường dùng chung cho Queen/Twin; chưa xác minh cấu hình riêng của hạng.')
            elif re.search('queen bed|comfortable queen',text,re.I): setbed(row,'1 Queen')
        elif h==57:
            own=between(lines,'View All Accommodation','OTHER ACCOMMODATION')
            # The room description and Size line precede the links to other categories.
            if not own:
                index=next((i for i,x in enumerate(lines) if x.startswith('Size:')),None)
                if index is not None: own=lines[max(0,index-2):index+3]
            text='\n'.join(own);setarea(row,next((x for x in own if x.startswith('Size:')),''))
            if re.search('one king size bed and two twin beds',text,re.I): setbed(row,'1 King và 2 giường đơn')
            elif re.search('king bed or two twin beds|king size bed or two twin beds',text,re.I): setbed(row,'1 King hoặc 2 giường đơn')
            elif re.search('two twin beds',text,re.I): setbed(row,'2 giường đơn')
        elif h==59:
            # Facts are explicitly curated from the category cards. Avoid related-room content.
            text=''
        elif h==60:
            text='\n'.join(between(lines,row['name'],'OTHER ROOMS'))
        row['amenities']=list(dict.fromkeys(row['amenities']+amenities(text)))

    if h in [5,8]:
        web=CACHE/(key+'-web.txt')
        if web.exists():
            webtext=web.read_text(encoding='utf-8')
            clean=[re.sub(r'^L\d+:\s*','',x).strip() for x in webtext.splitlines() if re.match(r'^L\d+:',x)]
            clean=[x for x in clean if x]
            bed=after(clean,'Bed types');occ=after(clean,'Max occupancy')
            if bed: setbed(row,bed)
            if occ:
                row['occupancy_text']=occ
                guests=re.search(r'Up to (\d+) guests',occ,re.I)
                if guests: row['capacity']=int(guests[1])
                else:
                    adults=re.search(r'(\d+) adults?',occ,re.I);child=re.search(r'(\d+) child',occ,re.I)
                    if adults: row['capacity']=int(adults[1])+(int(child[1]) if child else 0)
            measure=next((x for x in clean if 'Room area:' in x),None)
            if measure: setarea(row,measure.replace('m^{2}','m²').replace('sq.m','sqm'))
            elif h==5 and row['source_key']=='century-suite': setarea(row,'60 m2')
            row['amenities']=list(dict.fromkeys(row['amenities']+amenities('\n'.join(clean))))
            row['evidence_sha256'].append(hashlib.sha256(web.read_bytes()).hexdigest())

    # A source page and its check date are required even when only name/layout is established.
    if not row['evidence_sha256']:
        choices=[]
        if h in [3,6,7,13,16,17,45,51,61]: choices=[str(h)+'-web.txt']
        elif h==19: choices=['19-verified.txt']
        elif h==44: choices=['44-ota.txt']
        elif h==54: choices=['54-current.txt']
        elif h==56: choices=['56-verified.txt']
        elif h in [5,8]: choices=[str(h)+'-web.txt']
        for filename in choices:
            path=CACHE/filename
            if path.exists(): row['evidence_sha256'].append(hashlib.sha256(path.read_bytes()).hexdigest())
        main=read(str(h))
        if main and main['lines']: row['evidence_sha256'].append(main['sha256'])
        for candidate in CACHE_PAGES:
            if candidate.get('url') in [row['source_url'],row['catalog_source_url']] and candidate.get('lines'):
                row['evidence_sha256'].append(candidate['sha256'])
    if not row['evidence_sha256']: raise ValueError('Missing evidence '+key)
    if h in [13,45]:
        for source in CACHE.glob(str(h)+'-web-*.txt'):
            row['evidence_sha256'].append(hashlib.sha256(source.read_bytes()).hexdigest())
        if h==45 and row['source_key']=='3-bedroom-garden-view-villa':
            row['issues'].append('Nguồn ghi cả 349–393 m² và 350 m² cho cùng hạng; để trống diện tích chờ xác nhận.')

    # Known ambiguity is kept out of new data rather than resolved by a guess.
    if h==2:
        row['issues'].append('Website Rooms và Rates có một số sức chứa khác nhau; không ghi đè sức chứa cũ.')
        if row['source_key'] in ['premium-suite-large-balcony-city-view','royal-panorama-suite-city-view']:
            row['capacity']=2;row['occupancy_text']='Tối đa 2 khách theo Rooms và Rates'
    if h==19 and row['existing_room_type_ids']:
        if row['source_key']=='deluxe-double': row.update(area_min_m2=25,area_max_m2=25,bed_type='1 Queen')
        elif row['source_key']=='deluxe-twin': row.update(area_min_m2=25,area_max_m2=25,bed_type='2 giường đơn')
        else:
            # The existing Deluxe Suite cannot safely be equated with Booking's King Suite.
            continue
    if h==44 and row['existing_room_type_ids']:
        # Preserve these existing Hilton categories; this crawl cannot reverify their details.
        continue
    if h==56 and row['existing_room_type_ids']: continue
    if h==21 and row['source_key']=='deluxe':
        # This name is already archived in the hotel. Do not revive it or duplicate it.
        continue
    if h==49:
        facts={'standard-room':(35,'2 giường đơn'), 'superior-room':(35,'1 giường đôi hoặc 2 giường đơn'),
            'deluxe-room':(None,None),'villa-05-phong-ngu-huong-ho':(350,None),
            'villa-03-phong-ngu-huong-ho':(160,None),'phong-classic-huong-ho':(22,'1 giường đôi')}
        if row['source_key'] in facts:
            size,bed=facts[row['source_key']];row.update(area_min_m2=size,area_max_m2=size,bed_type=bed)
    if h==50:
        facts={'deluxe-mountain-view':(35,35,'King hoặc Twin'),'deluxe-golf-view':(35,35,'Double hoặc Twin hoặc Hollywood'),
            'studio-mountain-view':(40,44,'King hoặc Twin'),'family-deluxe-mountain-view':(70,70,None)}
        if row['source_key'] in facts:
            lo,hi,bed=facts[row['source_key']];row.update(area_min_m2=lo,area_max_m2=hi,bed_type=bed)
    if h==1 and row['source_key']=='deluxe-triple': row['bed_type']='1 Double và 1 Single'
    if h==9 and row['source_key']=='two-bedroom-seaside-villa-on-the-rocks': row['bed_type']='1 King và 2 giường đơn'
    if h==11 and row['source_key']=='deluxe-city-view': row['bed_type']='1 Queen hoặc 1 King hoặc 2 giường đơn'
    if h==21 and row['source_key'] in ['family-superior','family-superior-plus','family-deluxe']: row['bed_type']='1 King và 2 giường đơn'
    if h==51 and row['source_key'] in ['1-bedroom-residence','1-bedroom-ocean-view-residence']:
        row['area_min_m2'],row['area_max_m2']=54,71

    # Concise factual descriptions, authored from verified fields rather than copied marketing prose.
    parts=[f"Hạng {row['name']} tại {row['hotel_name']}."]
    if row['area_min_m2'] is not None:
        lo,hi=row['area_min_m2'],row['area_max_m2']
        parts.append('Diện tích '+(f'{lo:g}–{hi:g}' if lo!=hi else f'{lo:g}')+' m².')
    if row['bed_type']: parts.append('Bố trí giường: '+row['bed_type']+'.')
    if row['occupancy_text']: parts.append('Sức chứa theo nguồn: '+row['occupancy_text']+'.')
    if row['amenities']: parts.append('Tiện nghi đã xác minh: '+', '.join(row['amenities'])+'.')
    row['description']=' '.join(parts)
    row['issues'].append('Chưa xác minh giá cho ngày lưu trú và điều kiện cụ thể; loại mới ngừng bán, giá NULL.')
    row['issues'].append('Chưa có dữ liệu phân bổ tồn phòng vật lý theo hạng; không tạo Room.')
    if row['capacity'] is None: row['issues'].append('Chưa xác minh sức chứa tối đa; không suy ra từ số giường.')

    imgs=[]
    if page and h not in [14,49,54]:
        for img in rawimages(key,page):
            path=urlparse(img['url']).path.lower()
            if not re.search(r'\.(?:jpe?g|webp)$',path) or re.search(r'logo|icon|map|floor.?plan|placeholder|avatar|sprite|flag|award|footer|loader|loading|admin-ajax',path): continue
            if frequency[(str(h),img['url'])]==1: imgs.append(img)
        if h==57:
            # Room WEBPs precede page loaders and unrelated footer images in the gallery.
            imgs=[x for x in page['images'] if re.search(r'\.(?:jpe?g|webp)(?:\?|$)',x['url'],re.I)
                and re.search('room|villa|suite',x['url'],re.I) and not re.search('loading|logo|admin-ajax',x['url'],re.I)]
        if h==46:
            own=[x for x in page['images'] if '/rooms/'+row['source_key']+'/' in x['url'] and '_About_Room_Image.' in x['url']]
            if own: imgs=own
        # A room name in a caption is stronger evidence for galleries shared by several room pages.
        if h==58:
            needles={'panoramic-executive-suite':'executive suite panoramic','junior-suite':'junior suite - bedroom',
                'duplex-two-bedroom-apartment':'twin bedroom - two-bedroom apartment','sky-two-bedroom-apartment':'sky two-bedroom apartment master',
                'presidential-suite':'presidential suite - bedroom','deluxe-twin-room':'deluxe twin room - bedroom','premium-guest-room':'premium guest room - bedroom'}
            needle=needles.get(row['source_key'])
            if needle: imgs=[x for x in page['images'] if needle in x['alt'].lower()]
    if h in [47,53]:
        main=read(str(h)); needle=row['source_key']
        imgs=[x for x in main['images'] if needle in x['url'].lower() and '.jpg' in x['url'].lower()]
        if h==47 and needle=='executive': imgs=[x for x in imgs if 'premium-executive' not in x['url'].lower()]
        if h==47 and needle=='deluxe': imgs=[x for x in imgs if not re.search('premium|triple',x['url'],re.I)]
        if h==47 and needle=='premium-executive': imgs=sorted(imgs,key=lambda x:'premium-executive-1-' not in x['url'])
    # Keep at most one original source image per missing gallery. Existing configured galleries stay intact.
    if imgs:
        chosen=imgs[0]
        image_candidates.append({'key':key,'hotel_id':h,'source_key':row['source_key'],'source_url':chosen['url'],
            'page_url':row['source_url'],'caption':chosen['alt'],'checked_on':'2026-10-31',
            'local_path':'storage/accommodations/verified-room-types/20261031/'+key+('.webp' if urlparse(chosen['url']).path.lower().endswith('.webp') else '.jpg'),
            'verification':'Unique image in the exact room page gallery, or explicit room caption in official gallery.',
            'rights':'Nguồn công khai của cơ sở; chưa có xác nhận giấy phép tái sử dụng.'})
    manifest.append(row)

downloaded={}
if (WORK/'downloaded-images.json').exists():
    downloaded={x['key']:x for x in json.loads((WORK/'downloaded-images.json').read_text(encoding='utf-8')) if x.get('sha256')}
for row in manifest:
    key=str(row['hotel_id'])+'-'+row['source_key']
    selected=next((x for x in image_candidates if x['key']==key),None)
    if key in downloaded and selected and selected['source_url']==downloaded[key]['source_url']:
        row['images']=[{k:v for k,v in downloaded[key].items() if k not in ['key','hotel_id','source_key']}]
    else: row['issues'].append('Chưa có ảnh đúng hạng tải được và đối chiếu đủ; để trống gallery mới.')
output={'version':1,'checked_on':'2026-10-31','records':manifest}
target=ROOT/'database/data/verified-room-types-2026-10-31.json'
target.parent.mkdir(parents=True,exist_ok=True)
target.write_text(json.dumps(output,ensure_ascii=False,indent=2),encoding='utf-8')
(WORK/'image-candidates.json').write_text(json.dumps(image_candidates,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'records':len(manifest),'hotels':len(set(x['hotel_id'] for x in manifest)),
    'new_candidates':sum(not x['existing_room_type_ids'] for x in manifest),'image_candidates':len(image_candidates),
    'downloaded_images':len(downloaded),'with_capacity':sum(x['capacity'] is not None for x in manifest),
    'with_area':sum(x['area_min_m2'] is not None for x in manifest),'with_beds':sum(x['bed_type'] is not None for x in manifest)}))
