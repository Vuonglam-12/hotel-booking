<script>
let _currentRoomHotelId = null;
let roomTypeId = null, roomTypes = [], roomSaving = false, roomFormVersion = 0;
let roomTypesReady = false, roomCreating = false, roomCatalogVersion = 0, roomCatalogController;
let roomReturnFocus = null, roomParentHotel = false;
let roomInventoryModel = null;
let roomSelectionVersion=0, roomSelectionLoading=false;
const modalRoomImages = new AdminImageEditor('modal-room-images', () => updateRoomControls());
const modalRoomValidation=AdminRoomTypeValidation.editor(Object.fromEntries(Object.entries({name:'rtName',base_price:'rtPrice',capacity:'rtCapacity',bed_type:'rtBedType',total_rooms:'rtCount',floor_start:'rtFloor',status:'rtStatus',description:'rtDesc',amenities:'rtAmenities'}).map(([key,id])=>[key,document.getElementById(id)])),document.getElementById('rtValidationSummary'),()=>({model:roomInventoryModel,creating:roomCreating}));
function updateRoomControls(){
    const legacy=roomInventoryModel==='legacy';
    document.getElementById('rtSaveBtn').disabled=roomSaving||roomSelectionLoading||!roomTypesReady||(!legacy&&!modalRoomImages.ready)||(legacy?!roomCreating:!roomTypeId&&!roomCreating);
    document.getElementById('rtTypeSelect').disabled=roomSaving||!roomTypesReady;
    document.getElementById('rtNewType').disabled=roomSaving||!roomTypesReady;
    for(const id of ['rtName','rtPrice','rtCapacity','rtBedType','rtCount','rtStatus','rtDesc','rtFloor','rtAmenities'])document.getElementById(id).disabled=roomSaving||roomSelectionLoading||!roomTypesReady||(!roomTypeId&&!roomCreating)||(legacy&&!roomCreating);
    window.AdminRoomRates?.syncControls();
}
async function loadModalRoomTypes(hotelId, version, requestedType=null) {
    const requestVersion=++roomCatalogVersion;roomCatalogController?.abort();roomCatalogController=new AbortController();
    roomTypesReady=false;updateRoomControls();
    document.getElementById('rtListFeedback').textContent='Đang tải loại phòng của khách sạn…';
    document.getElementById('rtListFeedback').dataset.error='false';document.getElementById('rtListRetry').hidden=true;
    try {
        const response=await adminApi('/hotels/'+hotelId+'/room-types?inventory_model=auto',{signal:roomCatalogController.signal});
        const data=await response.json();
        if(version!==roomFormVersion||requestVersion!==roomCatalogVersion)return;
        if(!response.ok||!Array.isArray(data.room_types)||!['legacy','scoped','v2'].includes(data.inventory_model))throw new Error(response.status>=500?'Chức năng quản lý phòng chưa sẵn sàng ở môi trường này. Không thể tải danh sách để cập nhật ảnh.':data.message||'Không tải được loại phòng.');
        if(data.room_types.some(type=>Number(type.hotel_id)!==Number(hotelId)))throw new Error('Dữ liệu loại phòng không khớp khách sạn đang chọn.');
        roomTypes=data.room_types;roomTypesReady=true;roomInventoryModel=data.inventory_model;
        const legacy=roomInventoryModel==='legacy';
        document.getElementById('rtAmenitiesField').hidden=roomInventoryModel!=='scoped';
        document.getElementById('rtStatusField').hidden=legacy;
        document.getElementById('rtFloorField').hidden=!legacy;
        document.getElementById('rtCountLabel').textContent=legacy?'Số phòng cần tạo *':'Tổng số phòng có thể bán *';
        document.getElementById('rtCount').min=legacy?'1':'0';document.getElementById('rtCount').max=legacy?'100':'2147483647';
        document.getElementById('rtCapacity').max=legacy||roomInventoryModel==='scoped'?'10':'2147483647';document.getElementById('rtDesc').maxLength=legacy?500:65535;
        const select=document.getElementById('rtTypeSelect');
        select.replaceChildren(new Option(roomTypes.length?'Chọn loại phòng cần sửa':'Chưa có loại phòng',''),...roomTypes.map(type=>new Option(type.name,type.id)));
        document.getElementById('rtListFeedback').textContent=legacy?'Hiện hỗ trợ xem và thêm loại phòng từ các phòng của khách sạn. Chỉnh sửa loại phòng chưa khả dụng.':'';
        if(requestedType!==null){
            const type=roomTypes.find(type=>Number(type.id)===Number(requestedType));
            if(type){select.value=type.id;selectModalRoomType(type);}
            else{selectModalRoomType(null);document.getElementById('rtListFeedback').textContent='Loại phòng cần sửa không thuộc khách sạn đang chọn.';document.getElementById('rtListFeedback').dataset.error='true';}
        }else{
            selectModalRoomType(null,{create:roomTypes.length===0});
        }
        updateRoomControls();
    }catch(error){
        if(version!==roomFormVersion||requestVersion!==roomCatalogVersion||error.name==='AbortError')return;
        document.getElementById('rtListFeedback').textContent=error instanceof SyntaxError?'Máy chủ trả về dữ liệu không hợp lệ. Vui lòng thử lại.':error.message||'Không tải được loại phòng. Vui lòng thử lại.';
        document.getElementById('rtListFeedback').dataset.error='true';document.getElementById('rtListRetry').hidden=false;
        updateRoomControls();
    }
}
async function selectModalRoomType(type,{create=false}={}){
    if(roomSaving)return;
    if(type&&Number(type.hotel_id)!==Number(_currentRoomHotelId))return;
    const scoped=roomInventoryModel==='scoped', selection=++roomSelectionVersion;
    modalRoomImages.reset();
    roomSelectionLoading=false;
    if(scoped&&type){
        roomSelectionLoading=true;updateRoomControls();
        try{
            const response=await adminApi('/hotels/'+_currentRoomHotelId+'/room-types/'+type.id);
            const data=await response.json();if(selection!==roomSelectionVersion)return;
            if(!response.ok)throw new Error(data.message||'Không tải được loại phòng.');
            if(Number(data.room_type?.hotel_id)!==Number(_currentRoomHotelId)||Number(data.room_type?.id)!==Number(type.id))throw new Error('Loại phòng không thuộc khách sạn đang chọn.');
            type=data.room_type;roomSelectionLoading=false;
        }catch(error){if(selection===roomSelectionVersion){document.getElementById('rtListFeedback').textContent=error.message;document.getElementById('rtListFeedback').dataset.error='true';updateRoomControls();}return;}
    }
    roomTypeId=type?.id??null;roomCreating=create;
    window.AdminRoomRates?.select(_currentRoomHotelId,roomTypeId,roomInventoryModel);
    const legacy=roomInventoryModel==='legacy';
    document.getElementById('rtName').value=type?.name??'';document.getElementById('rtPrice').value=type?.base_price??'';
    document.getElementById('rtCapacity').value=type?.capacity??2;document.getElementById('rtBedType').value=type?.bed_type??'';
    document.getElementById('rtCount').value=type?.total_rooms??5;document.getElementById('rtStatus').value=type?.status??'active';document.getElementById('rtDesc').value=type?.description??'';
    document.getElementById('rtFloor').value=1;
    document.getElementById('rtAmenities').value=(type?.amenities||[]).join('\n');
    modalRoomFieldErrors();
    document.getElementById('rtFloorField').hidden=!legacy&&!scoped;
    document.getElementById('rtCountLabel').textContent=create&&(legacy||scoped)?'Số phòng cần tạo *':'Tổng số phòng quản lý';
    document.getElementById('rtCount').min=create&&(legacy||scoped)?'1':'0';
    document.getElementById('rtCount').max=legacy||(scoped&&create)?'100':scoped?'1000':'2147483647';
    document.getElementById('roomTypeModalTitle').textContent=type?(legacy?'Loại phòng: ':'Sửa loại phòng: ')+type.name:create?'Thêm loại phòng':'Quản lý loại phòng';
    document.getElementById('rtSaveBtn').textContent=create?'Tạo loại phòng':'Lưu thay đổi';
    document.getElementById('rtImagesField').hidden=legacy||(!type&&!create);
    if(!legacy&&(type||create)) await modalRoomImages.open(type?'/hotels/'+_currentRoomHotelId+'/room-types/'+type.id+'/images':null,{ordering:scoped});
    updateRoomControls();
}
function openRoomTypeModal(hotelId,typeId=null){
    if(roomSaving||hotelSaving)return;
    const hotelModal=document.getElementById('hotelModal');
    if(hotelModal.classList.contains('open')&&Number(editingId)!==Number(hotelId))return;
    if(!isRoomTypeModalOpen())roomReturnFocus=document.activeElement;
    roomParentHotel=hotelModal.classList.contains('open');
    const version=++roomFormVersion;_currentRoomHotelId=hotelId;roomTypes=[];roomTypesReady=false;roomCreating=false;roomInventoryModel=null;
    document.getElementById('rtHotelId').value=hotelId;
    document.getElementById('rtTypeSelect').replaceChildren(new Option('Đang tải loại phòng…',''));
    selectModalRoomType(null);document.getElementById('roomTypeModal').classList.add('open');
    document.getElementById('rtCloseBtn').focus();
    if(roomParentHotel){hotelModal.inert=true;hotelModal.setAttribute('aria-hidden','true');}
    syncAccommodationScroll();loadModalRoomTypes(hotelId,version,typeId);
}
function closeRoomTypeModal(){
    if(roomSaving)return;
    roomFormVersion++;roomCatalogVersion++;roomSelectionVersion++;roomSelectionLoading=false;roomCatalogController?.abort();
    modalRoomImages.reset();
    document.getElementById('roomTypeModal').classList.remove('open');
    window.AdminRoomRates?.select(null,null,null);
    if(roomParentHotel){const hotelModal=document.getElementById('hotelModal');hotelModal.inert=false;hotelModal.removeAttribute('aria-hidden');}
    roomParentHotel=false;syncAccommodationScroll();
    if(roomReturnFocus?.isConnected)roomReturnFocus.focus();roomReturnFocus=null;
}
function closeRoomTypeModalOnBg(event){if(event.target===document.getElementById('roomTypeModal'))closeRoomTypeModal();}
document.getElementById('rtTypeSelect').addEventListener('change',event=>selectModalRoomType(roomTypes.find(type=>Number(type.id)===Number(event.target.value))));
document.getElementById('rtNewType').addEventListener('click',()=>{document.getElementById('rtTypeSelect').value='';selectModalRoomType(null,{create:true});});
document.getElementById('rtListRetry').addEventListener('click',()=>loadModalRoomTypes(_currentRoomHotelId,roomFormVersion));
document.getElementById('roomTypeModal').addEventListener('keydown',event=>{
    if(event.key!=='Tab')return;
    const focusable=[...document.querySelectorAll('#roomTypeModal button:not([disabled]),#roomTypeModal select:not([disabled]),#roomTypeModal input:not([disabled]):not([type=hidden]),#roomTypeModal textarea:not([disabled])')].filter(element=>element.getClientRects().length);
    const first=focusable[0],last=focusable.at(-1);
    if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}
    else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}
});
function modalRoomFieldErrors(errors={}){
    if(Object.keys(errors).length)modalRoomValidation.show(errors);
    else modalRoomValidation.reset();
}
async function saveRoomType(){
    const legacy=roomInventoryModel==='legacy';
    if(roomSaving||roomSelectionLoading||!roomTypesReady||(!legacy&&!modalRoomImages.ready)||(legacy&&!roomCreating)||(!roomTypeId&&!roomCreating))return;
    const name=document.getElementById('rtName').value.trim(),price=document.getElementById('rtPrice').value;
    if(!modalRoomValidation.validate())return;
    const hotelId=_currentRoomHotelId,typeId=roomTypeId,version=roomFormVersion;
    const btn=document.getElementById('rtSaveBtn');
    try{
        const payload={inventory_model:roomInventoryModel,name,base_price:price,capacity:Number(document.getElementById('rtCapacity').value),bed_type:document.getElementById('rtBedType').value||null,total_rooms:Number(document.getElementById('rtCount').value),status:document.getElementById('rtStatus').value,description:document.getElementById('rtDesc').value||null};
        modalRoomFieldErrors();
        if(roomInventoryModel==='scoped'){
            payload.inventory_model='scoped';
            payload.amenities=[...new Set(document.getElementById('rtAmenities').value.split('\n').map(value=>value.trim()).filter(Boolean))];payload.floor_start=document.getElementById('rtFloor').value===''?null:Number(document.getElementById('rtFloor').value);if(!typeId){delete payload.total_rooms;payload.room_count=Number(document.getElementById('rtCount').value);}
        }
        if(legacy){delete payload.total_rooms;delete payload.status;payload.room_count=Number(document.getElementById('rtCount').value);payload.floor_start=document.getElementById('rtFloor').value===''?null:Number(document.getElementById('rtFloor').value);}
        const method=typeId?'PUT':'POST',body=legacy?JSON.stringify(payload):modalRoomImages.body(payload,method);
        roomSaving=true;updateRoomControls();btn.textContent='Đang lưu…';
        modalRoomImages.setSaving(true);
        document.querySelectorAll('#roomTypeModal [onclick="closeRoomTypeModal()"]').forEach(button=>button.disabled=true);
        const response=await adminApi('/hotels/'+hotelId+'/room-types'+(typeId?'/'+typeId:''),{method:body instanceof FormData?'POST':method,body});
        const data=await response.json();if(!response.ok){modalRoomFieldErrors(data.errors||{});throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Lưu loại phòng thất bại.');}
        if(version!==roomFormVersion)return;
        roomSaving=false;showToast('Đã lưu loại phòng.');closeRoomTypeModal();loadHotels(currentPage);
    }catch(error){if(version===roomFormVersion){document.getElementById('rtListFeedback').textContent=error.message||'Không kết nối được máy chủ.';document.getElementById('rtListFeedback').dataset.error='true';}}
    finally{roomSaving=false;modalRoomImages.setSaving(false);btn.textContent=typeId?'Lưu thay đổi':'Tạo loại phòng';updateRoomControls();document.querySelectorAll('#roomTypeModal [onclick="closeRoomTypeModal()"]') .forEach(button=>button.disabled=false);modalRoomValidation.focus();}
}
</script>
@include('admin.partials.room-rate-editor-script')
