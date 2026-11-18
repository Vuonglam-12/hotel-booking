const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
const history = fs.readFileSync(path.join(root, 'resources/views/user/bookings/bookings-script.blade.php'), 'utf8');
const hotel = fs.readFileSync(path.join(root, 'resources/views/admin/hotels.blade.php'), 'utf8');
const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'})[character]);

function namedFunction(source, name) {
    const declaration = new RegExp('^[ \\t]*(?:async )?function ' + name + '\\(', 'm').exec(source);
    assert.ok(declaration, 'Missing function ' + name);
    const start = declaration.index;
    const rest = source.slice(start);
    const next = /\n\s*(?:async )?function [A-Za-z]/g;
    next.lastIndex = rest.indexOf('{') + 1;
    const match = next.exec(rest);
    return match ? rest.slice(0, match.index) : rest.slice(0, rest.indexOf('</script>'));
}

function historyContext() {
    const node = () => ({innerHTML:'',querySelectorAll:()=>[]});
    const context = vm.createContext({bookingList:node(),detailContent:node(),selectedBookingId:null,reviewedBookingIds:new Set(),
        escapeHtml:escape,formatDate:String,formatPrice:amount=>Number(amount).toLocaleString('vi-VN')+' VND',getStatusText:String,
        HolidayVietImages:{cover:hotel=>hotel?.cover_image_url||'/image/placeholders/accommodation.svg',escapeAttribute:escape}});
    vm.runInContext(['roomSummary','renderBookingList','renderDetail'].map(name=>namedFunction(history,name)).join('\n'),context);
    return context;
}
function booking(quantity) {
    return {id:12,hotel:{id:1,name:'Fixture Hotel',cover_image_url:'/image/real.jpg'},booking_rooms:[{quantity,nights:2,room_type:{name:'Standard'}}],
        num_guests:2,total_price:1000000*quantity,status:'confirmed',check_in:'2026-12-01',check_out:'2026-12-03'};
}
for(const quantity of [1,2,3]) {
    test('History card renders stored quantity '+quantity+' and total',()=>{
        const context=historyContext();context.fixture=booking(quantity);vm.runInContext('renderBookingList([fixture])',context);
        assert.match(context.bookingList.innerHTML,new RegExp(quantity+' phòng'));
        assert.ok(context.bookingList.innerHTML.includes('Standard × '+quantity+' phòng'));
        assert.ok(context.bookingList.innerHTML.includes(Number(context.fixture.total_price).toLocaleString('vi-VN')+' VND'));
        assert.ok(context.bookingList.innerHTML.includes('/image/real.jpg'));
    });
    test('History detail renders stored quantity '+quantity+' and saved total independently of live price',()=>{
        const context=historyContext();context.fixture=booking(quantity);context.fixture.booking_rooms[0].room_type.base_price=900000;
        vm.runInContext('renderDetail(fixture)',context);
        assert.match(context.detailContent.innerHTML,new RegExp('Số phòng</span><span class="detail-value">'+quantity+'</span>'));
        assert.ok(context.detailContent.innerHTML.includes(Number(context.fixture.total_price).toLocaleString('vi-VN')+' VND'));
    });
}
test('Multiple booking lines sum quantities and preserve all type names',()=>{
    const context=historyContext();context.fixture=booking(2);context.fixture.booking_rooms.push({quantity:3,nights:2,room_type:{name:'Deluxe'}});
    const summary=vm.runInContext('roomSummary(fixture)',context);
    assert.equal(summary.quantity,5);assert.match(summary.labels,/Standard × 2 phòng, Deluxe × 3 phòng/);
});
test('Missing quantity is shown as unknown rather than guessed from relation count',()=>{
    const context=historyContext();context.fixture=booking(undefined);assert.equal(vm.runInContext('roomSummary(fixture).quantity',context),'—');
});
test('Missing image uses fixed placeholder in card and detail',()=>{
    const context=historyContext();context.fixture=booking(1);delete context.fixture.hotel.cover_image_url;
    vm.runInContext('renderBookingList([fixture]);renderDetail(fixture)',context);
    assert.ok(context.bookingList.innerHTML.includes('/image/placeholders/accommodation.svg'));
    assert.ok(context.detailContent.innerHTML.includes('/image/placeholders/accommodation.svg'));
    assert.ok(context.detailContent.innerHTML.includes('data-accommodation-image'));
    assert.doesNotMatch(history,/picsum|image_path|hotel-placeholder\.jpg/);
});

function hotelContext() {
    const nodes=new Map();const node=id=>{if(!nodes.has(id))nodes.set(id,{value:'',textContent:'',innerHTML:'',disabled:false,style:{},classList:{add(){},remove(){},toggle(){}}});return nodes.get(id);};
    const context=vm.createContext({document:{getElementById:node,querySelector:node,querySelectorAll:()=>context.checked.map(value=>({value}))},
        checked:[],editingId:null,currentPage:1,originalAmenityKeys:[],HOTEL_IMAGE_PLACEHOLDER:'/image/placeholders/accommodation.svg',
        hotelSaving:false,FormData,isRoomTypeModalOpen:()=>false,updateHotelRoomImagesEntry(){},selectHotelTab(){},syncAccommodationScroll(){},
        hotelImages:{ready:true,open(){},body:payload=>JSON.stringify(payload),setSaving(){},status(){}},
        AMENITY_LIST:[{key:'wifi',label:'WiFi'},{key:'room_service',label:'Room service'},{key:'airport_shuttle',label:'Airport shuttle'}],
        escHtml:escape,resetForm(){},validateForm:()=>true,closeModal(){},loadHotels(){},showToast(){},setTimeout(){},
        adminApi:async (url,options)=>{context.lastRequest={url,body:JSON.parse(options.body)};return {ok:true,json:async()=>({hotel:{id:1}})};}});
    const functions=['updateStats','renderAmenityGrid','getSelectedAmenities','openEditModal','saveHotel'];
    vm.runInContext(functions.map(name=>namedFunction(hotel,name)).join('\n'),context);
    return {context,node};
}
test('Unchanged amenities are omitted when saving unrelated Hotel fields',async()=>{
    const {context,node}=hotelContext();context.fixture={id:1,name:'Fixture',location_id:1,star_rating:3,address:'Test',amenities:[{amenity:'room_service'},{amenity:'airport_shuttle'},{amenity:'legacy_facility'}]};
    vm.runInContext('openEditModal(fixture)',context);context.checked=['legacy_facility','airport_shuttle','room_service'];node('f-name').value='Renamed';
    await vm.runInContext('saveHotel()',context);assert.equal(context.lastRequest.body.name,'Renamed');assert.equal('amenities' in context.lastRequest.body,false);
    assert.ok(node('amenityGrid').innerHTML.includes('legacy_facility'));
});
test('Clearing all amenity checkboxes sends explicit empty list',async()=>{
    const {context}=hotelContext();context.fixture={id:1,name:'Fixture',amenities:[{amenity:'wifi'}]};vm.runInContext('openEditModal(fixture)',context);
    await vm.runInContext('saveHotel()',context);assert.deepEqual(context.lastRequest.body.amenities,[]);
});
test('Legacy airport key is mapped to airport_shuttle without a silent replacement request',async()=>{
    const {context}=hotelContext();context.fixture={id:1,name:'Fixture',amenities:[{amenity:'airport'}]};vm.runInContext('openEditModal(fixture)',context);context.checked=['airport_shuttle'];
    await vm.runInContext('saveHotel()',context);assert.equal('amenities' in context.lastRequest.body,false);
});
test('KPI renders server aggregates and distinguishes closed or other statuses',()=>{
    const {context,node}=hotelContext();context.stats={total:21,active:11,inactive:9,closed:1,other:0,average_rating:4.7};vm.runInContext('updateStats(stats)',context);
    assert.equal(node('.js-stat-total').textContent,21);assert.equal(node('.js-stat-active').textContent,11);assert.equal(node('.js-stat-inactive').textContent,9);
    assert.match(node('.js-stat-status-note').textContent,/Đóng cửa: 1/);
    context.stats.other=2;vm.runInContext('updateStats(stats)',context);assert.match(node('.js-stat-status-note').textContent,/Trạng thái khác: 2/);
});
test('Missing KPI response does not manufacture statistics',()=>{
    const {context,node}=hotelContext();vm.runInContext('updateStats(undefined)',context);assert.equal(node('.js-stat-total').textContent,'—');assert.equal(node('.js-stat-rating').textContent,'—');
});
test('Hotel Detail stays gated and sends guest quantity to the availability API',()=>{
    const sidebar=fs.readFileSync(path.join(root,'resources/views/partials/hoteldetail/sidebar-script.blade.php'),'utf8');
    assert.match(sidebar,/submit-booking-btn'\)\.disabled = true/);
    assert.match(sidebar,/params\.set\('num_guests', guests\.value\)/);assert.match(sidebar,/params\.set\('quantity', quantity\.value\)/);
});
test('Customer dashboard and itinerary hotel cards no longer use random hotel photos',()=>{
    const dashboard=fs.readFileSync(path.join(root,'resources/views/Dashboard.blade.php'),'utf8');
    const itinerary=fs.readFileSync(path.join(root,'public/vendor/aurel-ai/chat.js'),'utf8');
    assert.doesNotMatch(dashboard,/picsum\.photos\/seed\/\$\{[bwrh]\./);
    assert.doesNotMatch(itinerary,/picsum|image_path/);
    assert.match(dashboard,/HolidayVietImages\.cover\(b\.hotel\)/);
    assert.match(itinerary,/photo\(media,item\.image\)/);
});
