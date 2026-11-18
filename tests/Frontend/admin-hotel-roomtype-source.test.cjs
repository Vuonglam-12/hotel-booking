const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const buffer = require('node:buffer');
const File = buffer.File || class extends buffer.Blob { constructor(parts,name,options){super(parts,options);this.name=name;} };
const source = fs.readFileSync(path.resolve(__dirname, '../../resources/views/admin/hotel-detail.blade.php'), 'utf8')
    .match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/@js\([^\n]+\)/g, expression => expression.includes('placeholder') ? JSON.stringify('/placeholder.svg') : '[]')
    .replace("document.addEventListener('DOMContentLoaded',loadHotel);", 'window.detailTest = {loadRoomTypes,openRoomEditor,saveRoomType,closeRoomEditor};');
const response = (status, data) => new Response(JSON.stringify(data), {status, headers:{'Content-Type':'application/json'}});
const type = {id:1, hotel_id:1, name:'Standard', base_price:'500000.25', capacity:2, total_rooms:5, status:'active'};
const tick = () => new Promise(resolve => setImmediate(resolve));

function harness(api) {
    const nodes = new Map(), calls = [], galleryCalls = [], editors = new Map();
    const get = id => {
        if (!nodes.has(id)) nodes.set(id, {dataset:{hotelId:'1'},value:'',textContent:'',innerHTML:'',hidden:false,disabled:false,handlers:{},files:[],clicks:0,src:'',parentElement:{appendChild(){}},
            classList:{add(){},remove(){}},addEventListener(name,handler){this.handlers[name]=handler;},click(){this.clicks++;},setAttribute(){},removeAttribute(){},scrollIntoView(){},focus(){}});
        return nodes.get(id);
    };
    const context = {window:null,document:{getElementById:get,addEventListener(){},querySelectorAll:()=>[]},
        location:{search:'',origin:'http://test.local'},URL,URLSearchParams,AbortController,FormData,File,setTimeout(){},
        AdminImageEditor:class {
            constructor(id,change){editors.set(id,this);this.change=change;this.reset();this.saving=false;}
            reset(){this.ready=false;this.fresh=[];this.deleted=new Set();this.primary=null;this.originalPrimary=null;}
            async open(endpoint){if(endpoint)galleryCalls.push(endpoint);this.ready=true;this.change();}
            setSaving(value){this.saving=value;this.change();}
            body(payload,method){
                const image_changes={delete_ids:[...this.deleted]};
                if(!this.fresh.length)return JSON.stringify({...payload,image_changes});
                const body=new FormData();for(const [key,value] of Object.entries(payload))body.append(key,value??'');
                body.append('image_changes',JSON.stringify(image_changes));for(const file of this.fresh)body.append('images[]',file);
                if(method!=='POST')body.append('_method',method);return body;
            }
            status(){}
        },
        adminBusy(button,busy){button.disabled=busy;},
        adminApi:async(url,options={})=>{calls.push({url,options});return api(url,options);}};
    context.window=context;vm.createContext(context);vm.runInContext(source,context);
    return {get,calls,galleryCalls,editors,detail:context.detailTest};
}

test('Detail tab loads legacy types, real room counts and explains unavailable editing', async()=>{
    const h=harness(async()=>response(200,{inventory_model:'legacy',room_types:[{...type,status:null}]}));
    await h.detail.loadRoomTypes();
    assert.equal(h.calls[0].url,'/hotels/1/room-types?inventory_model=auto');
    assert.equal(h.get('roomTypesTableWrap').hidden,false);assert.equal(h.get('addRoomType').hidden,false);
    assert.equal(h.get('roomTypesSourceNote').hidden,false);
    assert.ok(h.get('roomTypesBody').innerHTML.includes('500000.25'));
    assert.ok(h.get('roomTypesBody').innerHTML.includes('Theo từng phòng'));
    assert.ok(!h.get('roomTypesBody').innerHTML.includes('data-edit-room'));
    h.detail.openRoomEditor(type);assert.equal(h.galleryCalls.length,0);
});

test('Detail creation uses the legacy writer without status, inventory or image changes', async()=>{
    const h=harness(async(url,options)=>options.method?response(201,{rooms_created:3}):response(200,{inventory_model:'legacy',room_types:[]}));
    await h.detail.loadRoomTypes();await h.detail.openRoomEditor();
    assert.equal(h.get('saveRoomType').disabled,false);
    h.get('roomName').value='Legacy Deluxe';h.get('roomPrice').value='700000.25';h.get('roomCapacity').value='2';
    h.get('roomTotal').value='3';h.get('roomFloor').value='4';
    await h.detail.saveRoomType(h.get('saveRoomType'));
    const write=h.calls.find(call=>call.options.method);const payload=JSON.parse(write.options.body);
    assert.equal(write.url,'/hotels/1/room-types');assert.equal(write.options.method,'POST');
    assert.equal(payload.inventory_model,'legacy');assert.equal(payload.room_count,'3');assert.equal(payload.floor_start,'4');
    assert.equal(payload.base_price,'700000.25');assert.ok(!('total_rooms' in payload));assert.ok(!('status' in payload));
    assert.ok(!('image_changes' in payload));assert.equal(h.galleryCalls.length,0);
});

test('V2 guard failure remains an error with retry and never changes to legacy', async()=>{
    const h=harness(async()=>response(503,{code:'room_type_v2_unavailable',message:'V2 guard refused'}));
    await h.detail.loadRoomTypes();
    assert.equal(h.get('roomTypesError').hidden,false);assert.equal(h.get('addRoomType').hidden,true);
    assert.equal(h.get('roomTypesErrorText').textContent,'V2 guard refused');assert.equal(h.calls.length,1);
});

test('Detail loads a V2 room cover and keeps metadata editing', async()=>{
    const h=harness(async()=>response(200,{inventory_model:'v2',room_types:[type]}));
    await h.detail.loadRoomTypes();await h.detail.openRoomEditor(type);
    assert.deepEqual(h.galleryCalls,['/hotels/1/room-types/1/images']);
    assert.equal(h.get('roomStatusField').hidden,false);
    h.get('roomTotal').value='8';await h.detail.saveRoomType(h.get('saveRoomType'));
    const write=h.calls.find(call=>call.options.method);const payload=JSON.parse(write.options.body);
    assert.equal(write.url,'/hotels/1/room-types/1');assert.equal(write.options.method,'PUT');
    assert.equal(payload.inventory_model,'v2');assert.equal(payload.total_rooms,'8');assert.ok(!('room_count' in payload));
});

test('Room editor delegates upload and existing-image removal to the shared gallery contract',async()=>{
    const h=harness(async(url,options)=>options.method?response(200,{room_type:type}):response(200,{inventory_model:'v2',room_types:[type]}));
    await h.detail.loadRoomTypes();await h.detail.openRoomEditor(type);
    const editor=h.editors.get('hotel-room-images');
    editor.fresh=[new File(['photo'],'room-a.jpg',{type:'image/jpeg'})];editor.deleted.add(4);
    await h.detail.saveRoomType(h.get('saveRoomType'));
    const write=h.calls.find(call=>call.options.method);assert.equal(write.options.method,'POST');
    assert.equal(write.options.body.get('_method'),'PUT');assert.equal(write.options.body.getAll('images[]').length,1);
    assert.deepEqual(JSON.parse(write.options.body.get('image_changes')),{delete_ids:[4]});
});

test('Failed room save retains the gallery draft and enables retry',async()=>{
    let fail=true;
    const h=harness(async(url,options)=>options.method?(fail?(fail=false,response(422,{errors:{images:['Invalid image']}})):response(200,{})):response(200,{inventory_model:'v2',room_types:[type]}));
    await h.detail.loadRoomTypes();await h.detail.openRoomEditor(type);
    const editor=h.editors.get('hotel-room-images');editor.fresh=[new File(['photo'],'room.jpg',{type:'image/jpeg'})];
    await h.detail.saveRoomType(h.get('saveRoomType'));
    assert.equal(h.get('saveRoomType').disabled,false);assert.equal(editor.fresh.length,1);
    await h.detail.saveRoomType(h.get('saveRoomType'));
    const writes=h.calls.filter(call=>call.options.method);assert.equal(writes.length,2);assert.equal(writes[1].options.body.getAll('images[]').length,1);
});

test('Scoped editor loads fresh hotel-owned detail, sends metadata and keeps cancellation local',async()=>{
    const owned={...type,amenities:['Balcony']};
    const h=harness(async(url,options)=>options.method?response(200,{room_type:owned}):url.includes('inventory_model')?response(200,{inventory_model:'scoped',room_types:[owned]}):response(200,{room_type:owned}));
    await h.detail.loadRoomTypes();await h.detail.openRoomEditor(type);
    assert.equal(h.calls[1].url,'/hotels/1/room-types/1');assert.equal(h.get('roomAmenities').value,'Balcony');
    h.get('roomName').value='Canceled draft';h.detail.closeRoomEditor();
    assert.ok(h.calls.every(call=>!call.options.method));await h.detail.openRoomEditor(type);assert.equal(h.get('roomName').value,'Standard');
    h.get('roomName').value='Edited A';h.get('roomAmenities').value='Desk\nBalcony';
    await h.detail.saveRoomType(h.get('saveRoomType'));
    const payload=JSON.parse(h.calls.find(call=>call.options.method).options.body);
    assert.equal(payload.inventory_model,'scoped');assert.equal(payload.name,'Edited A');assert.deepEqual(payload.amenities,['Desk','Balcony']);
    assert.equal(payload.total_rooms,5);
});
