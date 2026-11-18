@push('styles')
<style>
#hotelModal>.modal,#roomTypeModal>.rt-modal{display:flex;flex-direction:column;overflow:hidden;max-height:92vh;max-height:92dvh;width:760px;max-width:calc(100vw - 24px)}
#hotelModal .modal-head,#hotelModal .modal-footer,#roomTypeModal .modal-header,#roomTypeModal .modal-footer{position:static;flex-shrink:0;background:#fff;z-index:2}
#hotelModal .modal-body,#roomTypeModal .modal-body{overflow-y:auto;min-height:0;overscroll-behavior:contain;flex:1;padding:18px 24px}
#hotelModal .modal-tabs{display:flex;gap:20px;padding:0 24px;border-bottom:1px solid #e5e7eb;flex-shrink:0}
#hotelModal .modal-tab{font:inherit;font-size:13px;font-weight:600;padding:12px 2px;border:0;border-bottom:2px solid transparent;background:#fff;color:#667085;cursor:pointer}
#hotelModal .modal-tab[aria-selected=true]{color:#2563eb;border-bottom-color:#2563eb}
#hotelModal [role=tabpanel][hidden]{display:none!important}#hotelModal #previewSection{margin:0}
#roomTypeModal .rt-selector{padding:14px 24px;border-bottom:1px solid #e5e7eb;flex-shrink:0}
#roomTypeModal .rt-selector label{font-size:12px;font-weight:600;display:block;margin-bottom:5px}
#roomTypeModal .rt-selector select{width:100%;padding:8px;border:1px solid #dce4ed;border-radius:8px;font:inherit;font-size:13px;background:#fff}
#roomTypeModal .form-row:last-child{margin-bottom:0}
#roomTypeModal #rtInfoFields[hidden]{display:none!important}
@media(max-width:600px){#hotelModal .modal-body,#roomTypeModal .modal-body{padding:16px}#hotelModal .modal-head,#roomTypeModal .modal-header,#hotelModal .modal-footer,#roomTypeModal .modal-footer{padding:14px 16px}#hotelModal .modal-tabs,#roomTypeModal .rt-selector{padding-left:16px;padding-right:16px}}
</style>
@endpush
