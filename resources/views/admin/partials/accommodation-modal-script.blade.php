<script>
let accommodationScrollState = null;
function isRoomTypeModalOpen(){return document.getElementById('roomTypeModal').classList.contains('open');}
function syncAccommodationScroll(){
    const open=!!document.querySelector('#hotelModal.open,#roomTypeModal.open');
    if(open&&accommodationScrollState===null){accommodationScrollState=document.body.style.overflow;document.body.style.overflow='hidden';}
    else if(!open&&accommodationScrollState!==null){document.body.style.overflow=accommodationScrollState;accommodationScrollState=null;}
}
function selectHotelTab(name){
    document.querySelectorAll('[data-hotel-tab]').forEach(button=>{const selected=button.dataset.hotelTab===name;button.setAttribute('aria-selected',String(selected));button.tabIndex=selected?0:-1;});
    document.getElementById('hotel-info-panel').hidden=name!=='info';document.getElementById('previewSection').hidden=name!=='images';
    document.querySelector('#hotelModal .modal-body').scrollTop=0;
}
document.querySelectorAll('[data-hotel-tab]').forEach(button=>{
    button.addEventListener('click',()=>selectHotelTab(button.dataset.hotelTab));
    button.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;event.preventDefault();const name=event.key==='Home'?'info':event.key==='End'?'images':button.dataset.hotelTab==='info'?'images':'info';selectHotelTab(name);document.querySelector(`[data-hotel-tab="${name}"]`).focus();});
});
document.addEventListener('keydown',event=>{if(event.key==='Escape'){if(document.getElementById('roomTypeModal').classList.contains('open'))closeRoomTypeModal();else if(document.getElementById('hotelModal').classList.contains('open'))closeModal();}});
</script>
