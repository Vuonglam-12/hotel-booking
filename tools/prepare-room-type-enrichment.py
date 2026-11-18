"""Curated hotel-specific research plan. Facts are reviewed before import."""
import json
import pathlib
import re
import unicodedata

ROOT = pathlib.Path(__file__).resolve().parents[1]
WORK = ROOT / 'storage/app/private/room-type-enrichment-20261031'
CACHE = WORK / 'sources'
BEFORE = json.loads((WORK/'before.json').read_text(encoding='utf-8'))
HOTELS = {h['id']:h for h in BEFORE['hotels']}
URLS = json.loads((WORK/'source-urls.json').read_text(encoding='utf-8'))
PLAN = []

def slug(name):
    return re.sub('[^a-z0-9]+','-',unicodedata.normalize('NFKD',name).encode('ascii','ignore').decode().lower()).strip('-')

def add(h, name, needle=None, existing=(), source=None, **facts):
    source = source or str(h)
    cached = CACHE/(source+'.json')
    page = json.loads(cached.read_text(encoding='utf-8')) if cached.exists() else None
    url = URLS.get(str(h))
    if page: url = page['url']
    if needle:
        if needle.startswith('https://'): url = needle
        elif page:
            links = [x['url'] for x in page['links'] if needle in x['url'] and '#' not in x['url']]
            if not links: raise ValueError((h,name,needle))
            exact = [u for u in links if re.search(re.escape(needle)+r'(?:/|[?&#]|$)',u)]
            url = (exact or links)[-1]
    item = {'hotel_id':h,'hotel_name':HOTELS[h]['name'],'hotel_address':HOTELS[h]['address'],
        'source_key':slug(name),'name':name,'source_url':url,'catalog_source_url':page['url'] if page else URLS.get(str(h)),
        'checked_on':'2026-10-31','existing_room_type_ids':list(existing),
        'capacity':None,'occupancy_text':None,'bed_type':None,'area_min_m2':None,'area_max_m2':None,
        'amenities':[],'price_reference':None,'physical_inventory':None,'images':[],**facts}
    PLAN.append(item)

# Aliases refer only to existing hotel-scoped metadata, never remap Room or BookingRoom IDs.
for name,term,ids in [
 ('Studio Double','/studio-double',[4]),('Superior Double','/superior-double',[5]),
 ('Studio Twin','/studio-twin',[3]),('8 Beds Mixed Dorm','/mixed-dorm',[1,2]),
 ('Deluxe Double','/deluxe-double',[]),('MEANDER Suite','/meander-suite',[]),
 ('Deluxe Triple','/deluxe-triple',[]),('Quadruple Room','/quadruple',[]),
 ('8 Beds Female Dorm','/female-dorm',[])]: add(1,name,term,ids)

for name,term,ids in [
 ('Superior Double Room with Window','/16/',[1,2]),('Deluxe Double or Twin Room with Window','/12/',[]),
 ('Premium Suite Large Balcony City View','/13/',[]),('Family Room with Large Window','/14/',[3]),
 ('Royal Panorama Suite City View','/1010/',[]),('Family Suite, 1 Double and 2 Single Beds, City View','/1011/',[]),
 ('Royal Suite, 2 Double and 1 Single Bed','/1012/',[]),('Premier Royal Suite City View','/1013/',[])]: add(2,name,term,ids)

for name,ids,area,bed,capacity,occ in [
 ('Superior Double Or Twin',[1],[23,25],'1 Queen hoặc 2 giường đơn',3,'2 người lớn + 1 trẻ dưới 4 tuổi'),
 ('Deluxe Double or Twin',[2],[23,25],'1 Queen hoặc 2 giường đơn',3,'2 người lớn + 1 trẻ dưới 4 tuổi'),
 ('Executive Room',[3],[25,25],'1 giường đôi hoặc 2 giường đơn',2,'2 người lớn'),
 ('Junior Suite',[4],[30,32],'1 King',3,'2 người lớn + 1 trẻ dưới 4 tuổi'),
 ('Deluxe Triple',[],[33,35],'1 Queen và 1 giường đơn',4,'3 người lớn + 1 trẻ dưới 4 tuổi'),
 ('Club Junior Suite',[],[33,35],'1 King',3,'2 người lớn + 1 trẻ dưới 4 tuổi'),
 ('La Siesta Suite',[],[30,33],'1 King',3,'2 người lớn + 1 trẻ dưới 4 tuổi'),
 ('Duplex Suite',[],[35,45],'1 King',2,'2 người lớn'),
 ('Family Suite',[],[50,55],'1 King và 2 giường đơn',5,'4 người lớn + 1 trẻ dưới 4 tuổi'),
 ('Club Duplex Suite',[],[45,50],None,5,'4 người lớn + 1 trẻ dưới 4 tuổi')]:
    add(3,name,existing=ids,area_min_m2=area[0],area_max_m2=area[1],bed_type=bed,capacity=capacity,occupancy_text=occ)

for name,term,ids in [('Family Room','/family-room-',[4]),('Family Balcony Room','/family-balcony-',[]),
 ('Deluxe Double or Twin Room','/deluxe-double-or-twin-',[]),('Superior Double Room','/superior-double-',[]),
 ('Deluxe Double Room, Balcony','/deluxe-double-room-balcony',[1]),('Hanoian Penthouse','/hanoian-penthouse',[])]: add(4,name,term,ids)

for name,ids in [('Century Deluxe',[]),('Century City Deluxe',[1]),('Century Pool Deluxe',[]),
 ('Century River Deluxe',[2]),('Century Suite',[3]),('Century Majestic Suite',[]),('Century Presidential Suite',[])]:
    add(5,name,'https://www.majesticsaigon.com/our-deluxes-suites/'+slug(name)+'/',ids)

for name,ids,area,cap,bed in [
 ('Superior Room',[2],[22,22],3,'1 King hoặc 2 giường đơn'),('Deluxe Room',[3],[22,25],3,'1 King hoặc 2 giường đơn'),
 ('Executive Room',[1],[25,25],3,'1 King hoặc 2 giường đơn'),('Junior Suite',[4],[25,25],3,'1 King hoặc 2 giường đơn'),
 ('Deluxe Connecting Room',[],[50,50],6,'1 King và 2 giường đơn'),
 ('Executive Triple Room',[],[28,28],4,'1 King và 1 giường đơn hoặc 3 giường đơn'),
 ('Executive Connecting Room',[],[55,55],6,None),('The Owner’s Suite',[],[45,45],3,'1 King hoặc 2 giường đơn')]:
    add(6,name,existing=ids,area_min_m2=area[0],area_max_m2=area[1],bed_type=bed,capacity=cap,
        occupancy_text={3:'2 người lớn và 1 trẻ dưới 4 tuổi',4:'3 người lớn và 1 trẻ dưới 4 tuổi',6:'4 người lớn và 2 trẻ dưới 4 tuổi'}.get(cap) if name!='Executive Connecting Room' else '5 người lớn và 1 trẻ dưới 4 tuổi')

for name,ids,area,cap in [('Superior Room',[2],[25,25],3),('Deluxe Room',[3],[22,25],2),
 ('Executive Room',[1],[25,25],2),('Deluxe Connecting Room',[],[44,50],4),
 ('Executive Connecting Room',[],[44,50],4),('Grand Suite',[],[50,50],2),('La Siesta Suite',[],[75,75],2)]:
    add(7,name,existing=ids,area_min_m2=area[0],area_max_m2=area[1],capacity=cap,occupancy_text=str(cap)+' khách',
        bed_type='2 giường đôi hoặc 4 giường đơn' if cap==4 else '1 giường đôi hoặc 2 giường đơn')

for name,ids in [('Deluxe',[]),('Premium Deluxe',[1]),('Senior Deluxe',[2,3]),('Grand Executive',[4]),
 ('Luxury Suite',[]),('Royal Suite',[]),('Grand Executive Suite',[]),('Grand Suite',[])]:
    add(8,name,'https://www.hotelgrandsaigon.com/rooms-suites/'+slug(name)+'/',ids)

for name in ['Resort Classic Room Oceanview','Resort Classic Panoramic Room Oceanview','Club Panoramic Room Oceanview',
 'Resort Terrace Suite Oceanview','Club Terrace Suite Panoramic Oceanview','One-Bedroom Heavenly Penthouse',
 'One-Bedroom Seaside Villa by the Beach','One-Bedroom Seaside Villa on the Rocks','One-Bedroom Spa Lagoon Villa',
 'Two-Bedroom Seaside Villa on the Rocks']: add(9,name,'/room-suites/'+slug(name)+'/')

for name,term,ids in [('Superior room','/superior-room/',[1]),('Deluxe room','/deluxe-room/',[2]),
 ('Deluxe Partial Ocean View','/deluxe-partial-ocean-view/',[3]),('Deluxe Ocean View','/deluxe-ocean-view/',[]),
 ('Family Connecting Room','/family-connecting-room/',[]),('Junior Suite','/junior-suite/',[]),
 ('Junior Family Suite','/junior-family-suite/',[]),('Family Suite','/family-suite/',[]),
 ('Penthouse Suite','/penthouse-suite/',[]),('One-bedroom cottage','/one-bedroom-cottage/',[])]: add(10,name,term,ids)

for name,term in [('Superior Standard','/en/rooms/superior-standard/'),('Superior River View','/en/rooms/superior-river-view/'),
 ('Superior Partial River View','/en/rooms/superior-partial-river-view/'),('Deluxe City View','/en/rooms/deluxe-city-view/'),
 ('Deluxe River View','/en/rooms/deluxe-river-view/'),('Junior Suite River View','/en/rooms/junior-suite/'),
 ('Grand Suite River View','/en/rooms/grand-suite-river-view/'),('1-Bedroom Suite Apartment','/en/rooms/1-bedroom-suite-apartment/'),
 ('2-Bedroom Suite Apartment','/en/rooms/2-bedroom-suite-apartment/'),
 ('2-Bedroom Presidential Apartment','/en/rooms/2-bedroom-presidential-apartment/')]: add(11,name,term)

for name,term in [('Deluxe King Garden View','/deluxe-king-garden-view/'),('Deluxe Twin Garden View','/deluxe-twin-garden-view/'),
 ('Deluxe King Private Garden','/deluxe-king-private-graden/'),('Deluxe Twin Private Garden','/deluxe-twin-private-graden/'),
 ('Deluxe King Partial Seaview with Balcony','/deluxe-king-partial-seaview-with-balcony/'),
 ('Deluxe Twin Partial Seaview with Balcony','/deluxe-twin-partial-seaview-with-balcony/'),
 ('Deluxe Triple Garden View','/deluxe-triple-garden-view/'),('Deluxe Triple Mountain View with Balcony','/deluxe-triple-mountain-view-with-balcony/'),
 ('Premium King Sea View','/premium-king-sea-view/'),('Premium Twin Sea View','/premium-twin-sea-view/')]: add(12,name,term)

for name,area,bed in [('Deluxe Twin',46,'2 giường đơn'),('Deluxe King',46,'1 King'),
 ('Junior Suite King',86,'1 King'),('Deluxe King Ocean View',46,'1 King'),('Deluxe Twin Ocean View',46,'2 giường đơn')]:
    add(13,name,area_min_m2=area,area_max_m2=area,bed_type=bed,capacity=4,occupancy_text='4 khách; chính sách người lớn/trẻ em cần xác nhận')
for name in ['Villa 3-Bedroom','Villa 3-Bedroom Ocean View']:
    add(13,name,area_min_m2=340,area_max_m2=340,capacity=12,
        occupancy_text='12 khách theo nguồn; cần xác nhận điều kiện người lớn/trẻ em',amenities=['Hồ bơi riêng'])

for name,term in [('Suite Room Sea View (Double/Twin)','/product/suite/'),('Family Room','/product/family/'),
 ('Superior Room (Window/No Window – Twin/Double)','/product/superior/'),
 ('Deluxe Room (Triple/Double – Window/Balcony)','/product/deluxe/'),('Senior Room with Balcony (Double/Twin)','/product/senior/')]: add(14,name,term)

for name,term,ids in [('Deluxe Room','/deluxe-room-2/',[3]),('Deluxe Ocean View','/deluxe-ocean-view/',[]),
 ('Grand Deluxe Ocean','/the-club/',[]),('Premium Deluxe Ocean View','/sunrise-club-suite/',[]),
 ('Junior Suite Ocean View','/junior-suite/',[]),('Executive Suite Ocean View','/sunrise-spa-suite/',[])]: add(15,name,term,ids)

for name,ids,area,cap,bed in [('Garden-View One-Bedroom Villa',[1],87,3,'1 King; 2 giường đơn theo yêu cầu'),
 ('Ocean-View One-Bedroom Villa',[2,3],87,3,'1 King; 2 giường đơn theo yêu cầu'),
 ('Beachfront One-Bedroom Villa',[],87,3,'1 King; 2 giường đơn theo yêu cầu'),('Family Villa',[],108,4,'1 King; 2 giường đơn theo yêu cầu'),
 ('Ocean-View One-Bedroom Pool Villa',[],250,3,'1 King; 2 giường đơn theo yêu cầu'),
 ('Garden-View One-Bedroom Pool Villa',[],250,3,'1 King; 2 giường đơn theo yêu cầu'),
 ('Ocean-View Two-Bedroom Pool Villa',[],400,6,'2 King; giường đơn theo yêu cầu'),
 ('Garden-View Two-Bedroom Pool Villa',[],400,6,'2 King; giường đơn theo yêu cầu'),
 ('Garden-View Three-Bedroom Pool Villa',[],520,9,'3 King; giường đơn theo yêu cầu'),
 ('Ocean-View Three-Bedroom Pool Villa',[],520,9,'3 King; giường đơn theo yêu cầu')]:
    add(16,name,existing=ids,area_min_m2=area,area_max_m2=area,capacity=cap,bed_type=bed,
        occupancy_text={3:'3 người lớn hoặc 2 người lớn và 1 trẻ',4:'3 người lớn hoặc 2 người lớn và 2 trẻ',6:'6 người lớn hoặc 4 người lớn và 2 trẻ',9:'9 người lớn hoặc 6 người lớn và 3 trẻ'}[cap],
        amenities=['Hồ bơi riêng'] if 'Pool Villa' in name else [])

for name,term,ids,area,capacity in [('Deluxe Balcony Room','deluxe-balcony-room',[1],36,3),('Deluxe Garden Suite','deluxe-garden-suite',[2],42,3),
 ('Premium River Suite','premium-river-suite',[3],42,3),('Anantara Garden Suite','anantara-garden-suite',[4],84,4),
 ('Thu Bon River Suite','thu-bon-river-suite',[],58,3),('Anantara Two Bedroom River Suite','anantara-two-bedroom-river-suite',[],84,4)]:
    add(17,name,'https://www.anantara.com/en/hoi-an/rooms/'+term,ids,area_min_m2=area,area_max_m2=area,capacity=capacity,
        occupancy_text='3 người lớn hoặc 2 người lớn và 1 trẻ' if capacity==3 else ('Tối đa 4 người lớn' if 'Two Bedroom' in name else '3 người lớn và 1 trẻ hoặc 2 người lớn và 2 trẻ'))

for name,term,ids in [('Superior','the-superior-rooms',[2]),('Luxury Exclusive','luxury-exclusive',[1]),
 ('Luxury with Balcony','luxury-balcony',[]),('Suite','the-suites',[4]),('Presidential Suite','the-presidential-suites',[]),
 ('Royal Executive Suite','the-royal-executive-suite',[]),('Royal Superior','the-royal-superior',[]),
 ('Royal Luxury','the-royal-luxury',[]),('Royal Suite','the-royal-suites',[])]:
    add(18,name,'https://www.dalatpalacehotel.com/en/'+term+'/',ids)

# Existing room names at Le Recit are preserved; additional types require Booking evidence below.
for name,ids in [('Deluxe Double',[1]),('Deluxe Twin',[3]),('Deluxe Suite',[2])]: add(19,name,existing=ids)
add(19,'Two-Bedroom Chalet','https://www.booking.com/hotel/vn/le-recit-boutique-de-dalat.fr.html',
    area_min_m2=75,area_max_m2=75,bed_type='1 King và 1 Queen')

for name,term,ids,area,bed in [('Superior','/superior/',[1,2],26,'1 Queen hoặc 2 giường đơn'),
 ('Superior Garden View','/superior-garden-view/',[3],26,'1 Queen hoặc 2 giường đơn'),
 ('Superior River View','/superior-river-view/',[],26,'1 Queen hoặc 2 giường đơn'),
 ('Deluxe Room','/deluxe-room/',[],30,'1 King'),('Deluxe Garden View','/deluxe-garden-view/',[],30,'1 King hoặc 2 giường đơn'),
 ('Deluxe River View','/deluxe-river-view/',[],30,'1 King hoặc 2 giường đơn'),('Deluxe Colonial','/deluxe-colonial/',[],30,'1 King'),
 ('Colonial Junior Suite','/colonial-junior-suite/',[],46,'1 King'),('Le Jardin Suite','/le-jardin-suite/',[],46,'1 King'),
 ('Resident Suite','/resident-suite/',[],72,'1 King')]: add(20,name,term,ids,area_min_m2=area,area_max_m2=area,bed_type=bed,capacity=3,occupancy_text='2 người lớn và 1 trẻ em')

for name,ids in [('Superior',[1]),('Superior Plus',[2]),('Deluxe',[]),('Family Superior',[]),('Family Superior Plus',[]),
 ('Family Deluxe',[]),('Family Villa',[]),('Tropical Villa',[]),('Pool Access Villa',[]),('Plunge Pool Villa',[])]:
    add(21,name,'name='+slug(name).replace('-','-').title(),ids)

for name,ids,area,bed in [('Superior (Twin/King)',[13,14],30,'1 King hoặc 2 giường đơn'),
 ('Superior Twin/King Ocean View',[],30,'1 King hoặc 2 giường đơn'),('Deluxe (Twin/King)',[15],35,'1 King hoặc 2 giường đơn'),
 ('Deluxe King/Twin Ocean View',[],35,'1 King hoặc 2 giường đơn'),('Premier Ocean View',[],60,None)]:
    add(43,name,existing=ids,area_min_m2=area,area_max_m2=area,bed_type=bed,capacity=3,occupancy_text='2 người lớn và 1 trẻ em')
for name,area,capacity in [('Three-Bedroom Premier Suite',195,6),('Three-Bedroom Executive Suite',180,6),
 ('Three-Bedroom Deluxe Suite',135,6),('One-Bedroom Deluxe',65,2)]:
    add(43,name,source='43-appartment',area_min_m2=area,area_max_m2=area,capacity=capacity,
        occupancy_text=str(capacity)+' người lớn',amenities=['Bếp nhỏ','Tủ lạnh','Két an toàn','TV LCD'])

for name,ids in [('King Accessible Room',[16]),('Twin Guest Room',[17])]: add(44,name,existing=ids)
for name in ['King Guest Room','King Deluxe Room Pool Access','King Premium Suite with Riverside View',
 'Deluxe King Room with Balcony','Premium King Room - River Side View','King Guest Room with Balcony',
 'Premium Corner King Room','King Room with Riverside View']:
    area={'King Room with Riverside View':37,'King Guest Room with Balcony':34}.get(name)
    add(44,name,'https://www.booking.com/hotel/vn/nhaan-resort-spa-hoi-an-tapestry-collection-by-hilton.html',
        bed_type='1 King',area_min_m2=area,area_max_m2=area)
for name,ids,bed in [('Deluxe King',[18],'1 King'),('Deluxe 2 Single Beds',[19],'2 giường đơn'),
 ('Deluxe King Ocean View',[],'1 King'),('Deluxe Twin Ocean View',[],'2 giường đơn')]:
    add(45,name,existing=ids,area_min_m2=48,area_max_m2=48,bed_type=bed,capacity=4,occupancy_text='4 khách; chính sách người lớn/trẻ em cần xác nhận')
for name,size,capacity,bed in [('Executive Suite King',83,2,'1 King'),('2-Bedroom Garden View Villa',288,8,None),
 ('3-Bedroom Garden View Villa',None,12,None),('3-Bedroom Ocean View Villa',349,12,None)]:
    add(45,name,area_min_m2=size,area_max_m2=size,capacity=capacity,bed_type=bed,
        occupancy_text=str(capacity)+' khách theo nguồn; cần xác nhận điều kiện người lớn/trẻ em',
        amenities=['TV','Wi-Fi','Bồn tắm']+(['Bếp riêng','Hồ bơi riêng'] if 'Villa' in name else ['Tủ lạnh']))

for name,ids,area in [('Deluxe Room',[20],35),('Deluxe Lakeview Room',[21],35),('Premier Lakeview Room',[22],35),
 ('Pacific Club Room',[],35),('Pacific Club Lakeview Room',[],35),('Studio Suite',[],48),('Studio Balcony Suite',[],50),
 ('Premier Suite',[],90),('Executive Suite',[],100),('Pan Pacific Suite',[],287)]:
    add(46,name,('/'+slug(name)+'.html') if name!='Studio Suite' else '/studio-suite.html',ids,area_min_m2=area,area_max_m2=area)

for name,ids,area,bed in [('Deluxe',[23],25,'1 King hoặc 2 giường đơn'),('Premium Deluxe',[24],25,'1 King hoặc 2 giường đơn'),
 ('Triple',[25],35,'3 giường đơn hoặc 1 King và 1 giường đơn'),('Executive',[],35,'1 Queen'),
 ('Premium Executive',[],40,'1 King hoặc 2 giường đơn'),('Presidential Suite',[],None,None)]:
    add(47,name,existing=ids,area_min_m2=area,area_max_m2=area,bed_type=bed)

for name,ids in [('Le Petit',[26]),('Villa Room',[27]),('Villa Studio',[28]),('Villa Suite',[]),
 ('Grande Suite',[]),('Bellevue Suite',[]),('Ana Mandara Suite',[])]: add(48,name,'/'+slug(name)+'/',ids)

for name,term,ids,source in [('Standard Room','/phong-standard-37.html',[29],'49'),('Superior Room','/phong-superior-38.html',[30],'49'),
 ('Deluxe Room','/phong-deluxe-39.html',[31],'49'),('Villa 05 phòng ngủ hướng hồ','/villa-05-phong-ngu-huong-ho-43.html',[],'49-villas'),
 ('Villa 04 phòng ngủ hướng hồ','/villa-04-phong-ngu-huong-ho-42.html',[],'49-villas'),
 ('Villa 03 phòng ngủ hướng hồ','/villa-03-phong-ngu-huong-ho-41.html',[],'49-villas'),
 ('Phòng Premium Villa - Hướng Hồ','/phong-premium-villa-huong-ho-55.html',[],'49-villas'),
 ('Phòng Classic Hướng Hồ','/phong-classic-huong-ho-40.html',[],'49-villas')]: add(49,name,term,ids,source)

for name,ids in [('Deluxe Mountain View',[32]),('Deluxe Golf View',[33]),('Studio Mountain View',[34]),
 ('Family Deluxe Mountain View',[])]: add(50,name,existing=ids,source='50-sam')
for name,size,bed in [('King Suite Mountain View',74,'King'),('Royal Suite Mountain View',118,'King và Twin')]:
    add(50,name,'/'+slug(name)+'/',source='50-page2',area_min_m2=size,area_max_m2=size,bed_type=bed)

for name,ids,area,bed in [('1 King Bed',[35],40,'1 King'),('2 Twin Beds',[36],40,'2 giường đơn'),
 ('1 King Bed Ocean View',[37],40,'1 King'),('2 Twin Beds Ocean View',[],40,'2 giường đơn'),
 ('2 Twin Beds with Garden',[],40,'2 giường đơn'),('1 Bedroom Residence',[],None,None),
 ('1 Bedroom Ocean View Residence',[],None,None),('Regency Ocean View Suite',[],80,None),('Regency Suite',[],80,None),
 ('2 Bedroom Residence',[],105,None)]:
    add(51,name,existing=ids,area_min_m2=area,area_max_m2=area,bed_type=bed)
# Club access alone is not added as another physical category without evidence of different rooms.

for name,ids,area,bed in [('Superior Room, City View - 2 Single Beds',[38],28,'2 giường đơn'),
 ('Superior Room, Balcony, Han River View/Bay View - 1 King Bed',[39],28,'1 giường đôi'),
 ('Deluxe Room, Balcony, Dragon Bridge View - 1 King Bed',[40],28,'1 giường đôi'),
 ('Deluxe Room, City View - 2 Single Beds',[],39,'2 giường đơn'),
 ('Superior Room, Balcony, Han River View/Bay View - 2 Single Beds',[],28,'2 giường đơn'),
 ('Deluxe Room, Balcony, Dragon Bridge View - 2 Single Beds',[],28,'2 giường đơn'),
 ('Novotel Suite, Balcony, Han River View',[],56,'1 King'),('Corner Suite, Panoramic Han River View',[],57,'1 King'),
 ('Studio Apartment, Balcony, Han River View',[],28,'1 King'),('1 Bedroom Apartment, Balcony',[],56,'1 King')]:
    add(52,name,existing=ids,area_min_m2=area,area_max_m2=area,bed_type=bed)

for name,ids,lo,hi,bed in [('Deluxe',[],24,24,'1 King hoặc 2 giường đơn'),('Classic',[41],23,28,'1 King hoặc 2 giường đơn'),
 ('Premium Classic',[42],28,30,'1 King hoặc 2 giường đơn'),('Triple Classic',[43],38,38,'1 King và 1 giường đơn'),
 ('Family Connecting Room',[],48,48,'1 King và 2 giường đơn'),('Junior Suite',[],55,57,'1 King'),
 ('Executive Suite',[],72,72,'1 King'),('Presidential Suite',[],114,114,'1 King và 2 giường đơn')]:
    add(53,name,existing=ids,area_min_m2=lo,area_max_m2=hi,bed_type=bed)

for name,ids in [('Deluxe Room',[44]),('Premium Room',[45]),('Suite',[46])]: add(54,name,existing=ids)
for name,bed in [('Premium Room With View','1 King hoặc 2 giường đơn'),('Suite, 2 Bedrooms','1 King và 2 giường đơn'),
 ('Presidential Suite','1 King và 2 giường đơn'),('Deluxe Room, 2 Bedrooms','1 King và 2 giường đơn')]:
    add(54,name,'https://www.expedia.com/Hue-Hotels-Melia-Vinpearl-Hue.h29267758.Hotel-Information',bed_type=bed)

for name,needle,ids in [('Premium Double Without View','/premium-double-without-view.html',[]),('Premium Senna Double','/premium-senna.html',[47]),
 ('Premium Garden View Twin','/premium-garden-view-twin.html',[48]),('Premium City View Twin','/PREMIUM-CITY-VIEW-TWIN.html',[]),
 ('Premium Garden View Double','/PREMIUM-GARDEN-VIEW-DOUBLE.html',[]),('Premium City View Double','/PREMIUM-CITY-VIEW-DOUBLE.html',[]),
 ('Premier Suite','/premier-suite.html',[]),('Executive Suite','/executive-suite.html',[49]),('Presidential Suite','/presidential.html',[])]:
    add(55,name,needle,ids)

for name,ids in [('Classic Room',[50]),('Premium Room',[51]),('Junior Suite',[52])]: add(56,name,existing=ids)
for name,area,bed in [('King Suite City View',None,None),('1 Bedroom Family Ocean View Bunkbeds',53,'1 King và 1 giường tầng'),
 ('Panorama Ocean View 1 Bedroom Suite',96,'1 King'),('Presidential Suite with Panoramic Ocean View',None,None)]:
    add(56,name,'https://nhatrang.intercontinental.com/rooms/',area_min_m2=area,area_max_m2=area,bed_type=bed)

for name,ids in [('Deluxe Room Garden View',[53]),('Deluxe Room Ocean View With Children Themed Room',[54]),
 ('Family Room 2-Bedroom Garden View',[55]),('Deluxe Room Garden View With Children Themed Room',[]),
 ('Deluxe Room Mountain View With Children Themed Room',[]),('Family Room 2-Bedroom Ocean View',[]),
 ('Family Suite 2-Bedroom Garden View',[]),('Family Suite 2-Bedroom Ocean View',[]),('Deluxe Villa Garden View',[]),
 ('Premier Deluxe Villa Garden View',[])]: add(57,name,'/accommodation/'+slug(name),ids)

for name,ids in [('Deluxe Room With King Bed',[56]),('Executive Suite',[57])]: add(58,name,existing=ids)
for name,area,bed,capacity in [('Panoramic Executive Suite',None,None,None),('Junior Suite',None,None,None),
 ('Duplex Two-Bedroom Apartment',174,'1 King và 2 giường đơn',4),('Sky Two-Bedroom Apartment',182,'2 King',None),
 ('Presidential Suite',182,None,None)]:
    add(58,name,'https://www.marriott.com/en-us/hotels/nhasi-sheraton-nha-trang-hotel-and-spa/rooms/premium-rooms/',
        area_min_m2=area,area_max_m2=area,bed_type=bed,capacity=capacity,
        occupancy_text='Tối đa 4 khách' if capacity else None)
for name,bed in [('Deluxe Twin Room','2 giường đơn'),('Premium Guest Room',None)]:
    add(58,name,'https://www.marriott.com/en-us/hotels/nhasi-sheraton-nha-trang-hotel-and-spa/photos/',bed_type=bed)

for name,ids,area,cap,bed in [('Deluxe Room',[58],43,3,'1 King hoặc 2 giường đơn'),('Deluxe Garden View',[59],43,3,'1 King hoặc 2 giường đơn'),
 ('Deluxe Hill View',[60],43,3,'1 King hoặc 2 giường đơn'),('Deluxe Sea View',[],43,3,'1 King hoặc 2 giường đơn'),
 ('Premium Deluxe Sea',[],43,3,'1 King hoặc 2 giường đơn'),('Garden Villa',[],65,2,'1 King hoặc 2 giường đơn'),
 ('Suite Sea View Hotel',[],87,2,'1 King'),('Pearl Suite Villa',[],100,2,'1 King')]:
    term={'Premium Deluxe Sea':'premium-deluxe-sea-view'}.get(name,slug(name))
    add(59,name,'/accommodation/'+term,ids,area_min_m2=area,area_max_m2=area,capacity=cap,occupancy_text=str(cap)+' khách' if cap==3 else 'Tối đa 2 người lớn',bed_type=bed)

for name,term,ids,lo,hi,bed,source in [('Resort Classic','resort-classic',[61],42,51,'1 King hoặc 2 giường đơn','60-own'),
 ('Resort Classic Ocean View','resort-classic-ocean-view',[],46,51,'1 King hoặc 2 giường đơn','60-own'),
 ('Junior Suite','junior-suite',[62],64,90,'1 King hoặc 2 giường đơn','60-own'),
 ('Junior Suite Ocean View','junior-suite-ocean-view',[],73,79,'1 King hoặc 2 giường đơn','60-own'),
 ('Family Suite','family-suite',[],131,133,'1 King và 2 giường đơn','60-own'),
 ('Penthouse Suite','penthouse-suite',[],94,94,'1 King','60-royal'),('Bridge Suite','bridge-suite',[],305,305,None,'60-royal'),
 ('3-Bedroom Villa','3-bedroom-villa',[],426,426,None,'60-royal'),('4-Bedroom Villa','4-bedroom-villa',[],494,494,None,'60-royal')]:
    add(60,name,'/accommodation/'+term+'/',ids,source,area_min_m2=lo,area_max_m2=hi,bed_type=bed)
# Residence is a collection of multiple layouts; do not add it as one extra room type.

for name,ids,area,bed in [('Ocean View Suite',[64],78,'1 King hoặc 2 giường đơn'),('King Sky Pool Villa',[65],165,'1 King'),
 ('King Beach Pool Villa',[],100,'1 King'),('King Terrace Pool Villa',[],208,'1 King'),('Garden Pool Suite',[],122,'1 King hoặc 2 giường đơn'),
 ('Three Bedroom Beach Pool Villa',[],733,'2 King và 2 giường đơn'),('Two Bedroom Lagoon Pool Villa',[],317,'1 King và 2 giường đơn'),
 ('Two Bedroom Sky Pool Villa',[],223,'1 King và 2 giường đơn'),('Two Bedroom Terrace Pool Villa',[],385,'1 King và 2 giường đơn'),
 ('Three Bedroom Lagoon Pool Villa',[],446,'2 King và 2 giường đơn')]:
    add(61,name,existing=ids,area_min_m2=area,area_max_m2=area,bed_type=bed)

if __name__=='__main__':
    keys=[(x['hotel_id'],x['source_key']) for x in PLAN]
    assert len(keys)==len(set(keys))
    (WORK/'research-plan.json').write_text(json.dumps(PLAN,ensure_ascii=False,indent=2),encoding='utf-8')
    details={str(x['hotel_id'])+'-'+x['source_key']:x['source_url'] for x in PLAN
        if x['source_url']!=x['catalog_source_url']}
    (WORK/'detail-urls.json').write_text(json.dumps(details,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps({'planned_types':len(PLAN),'hotels':len(set(x['hotel_id'] for x in PLAN)),'detail_pages':len(details)}))
