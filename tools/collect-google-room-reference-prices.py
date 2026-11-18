"""Extract review candidates from public room-specific offers; never infer other room prices."""
import collections,csv,decimal,json,pathlib,re
root=pathlib.Path(__file__).resolve().parents[1];work=root/'storage/app/private/room-admin-update-20261102';sources=work/'sources'
rows=[];summary=[]
for file in sorted(sources.glob('google-*.json')):
    data=json.loads(file.read_text(encoding='utf8'));text=data.get('all_text','');hotel=data.get('hotel_id',61 if 'regent' in file.name else None)
    if hotel is None or data.get('challenge') or not text:continue
    # Each offer contains displayed current nightly total twice and a separate stay total.
    offers=[]
    pattern=r'(?:Visit site|Get rewards|Get price)([^₫]{1,700}?)₫([\d,]+)₫([\d,]+)₫([\d,]+)'
    for match in re.finditer(pattern,text):
        offer,first,nightly,total=match.groups();offer=re.sub(r'\s+',' ',offer).strip()
        name,_,plan=offer.partition(' - ')
        first,nightly,total=[int(s.replace(',','')) for s in [first,nightly,total]]
        check_in=data.get('check_in','2026-11-15');check_out=data.get('check_out','2026-11-17')
        from datetime import date
        nights=(date.fromisoformat(check_out)-date.fromisoformat(check_in)).days
        if first!=nightly or abs(nightly*nights-total)>max(2,nights):continue
        if any(x in name.lower() for x in ['http','sponsored','featured options']):continue
        # Duplicate hidden desktop/mobile renderings are one quote.
        identity=(name.lower(),plan,nightly,total)
        if identity in offers:continue
        offers.append(identity)
        rows.append({'hotel_id':hotel,'source_room_name':name,'source_plan':plan,'offer_text':offer,'nightly_reference_vnd':str(nightly)+'.00','stay_total_vnd':str(total),'nights':nights,'check_in':check_in,'check_out':check_out,'checked_on':data['checked_on'],'source_url':data['url'],'evidence_path':str(file.relative_to(root)).replace('\\','/'),'source_title':data['title']})
    summary.append({'hotel_id':hotel,'file':file.name,'offers':len(offers),'title':data['title']})
(work/'google-price-candidates.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2),encoding='utf8')
with (work/'google-price-candidates.csv').open('w',encoding='utf-8-sig',newline='') as f:
    if rows:
        writer=csv.DictWriter(f,fieldnames=list(rows[0]));writer.writeheader();writer.writerows(rows)
print(json.dumps({'candidates':len(rows),'sources':summary},ensure_ascii=True))
