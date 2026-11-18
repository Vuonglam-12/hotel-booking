<style>
    .room-rate-editor{margin-top:20px;padding-top:18px;border-top:1px solid #e4eaf1;color:#243247;font-family:inherit;min-width:0}
    .room-rate-editor>summary{cursor:pointer;font-size:14px;font-weight:700;line-height:1.6}
    .room-rate-editor .rr-context{margin:7px 0 14px;font-size:12px;color:#667085;line-height:1.6;overflow-wrap:anywhere}
    .room-rate-editor .rr-base{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:13px 15px;margin-bottom:16px;border:1px solid #e4eaf1;border-radius:10px;background:#f8fafc}
    .room-rate-editor .rr-base strong{display:block;font-size:13px}.room-rate-editor .rr-base small{display:block;margin-top:3px;color:#667085;font-size:11px}
    .room-rate-editor .rr-base output{flex-shrink:0;font-size:15px;font-weight:700;font-variant-numeric:tabular-nums;text-align:right}
    .room-rate-editor .rr-card{padding:16px;border:1px solid #e4eaf1;border-radius:11px;background:#fff;min-width:0}
    .room-rate-editor .rr-card+.rr-card{margin-top:14px}.room-rate-editor .rr-card h4{margin:0;font-size:13px;font-weight:700}
    .room-rate-editor .rr-note{margin:7px 0 14px;color:#667085;font-size:12px;line-height:1.65}
    .room-rate-editor .rr-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px 14px}
    .room-rate-editor .rr-field{min-width:0}.room-rate-editor .rr-field[hidden]{display:none!important}
    .room-rate-editor label{display:block;margin:0 0 6px;font-size:12px;font-weight:600;color:#344054}
    .room-rate-editor input,.room-rate-editor select{display:block;width:100%;max-width:100%;box-sizing:border-box;min-height:40px;padding:9px 10px;border:1px solid #cfd8e3;border-radius:8px;background:#fff;color:#243247;font:inherit;font-size:13px}
    .room-rate-editor input:focus,.room-rate-editor select:focus{outline:2px solid #bfdbfe;border-color:#2563eb}
    .room-rate-editor .rr-field small{display:block;margin-top:5px;font-size:11px;color:#667085}
    .room-rate-editor .rr-actions,.room-rate-editor .rr-list-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
    .room-rate-editor .rr-actions{justify-content:flex-start;margin-top:15px}
    .room-rate-editor .rr-button{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:7px 11px;border:1px solid #d6dfeb;border-radius:8px;background:#fff;color:#344054;font:inherit;font-size:12px;font-weight:600;text-decoration:none;cursor:pointer;white-space:nowrap}
    .room-rate-editor .rr-button:hover{background:#f1f5f9}.room-rate-editor .rr-button:disabled{opacity:.55;cursor:wait}
    .room-rate-editor .rr-button-primary{background:#2563eb;border-color:#2563eb;color:#fff}.room-rate-editor .rr-button-primary:hover{background:#1d4ed8}
    .room-rate-editor .rr-button-delete{color:#b42318;border-color:#fecaca}.room-rate-editor .rr-button-delete:hover{background:#fff1f2}
    .room-rate-editor .rr-feedback{font-size:12px;line-height:1.6;color:#475467;overflow-wrap:anywhere}.room-rate-editor .rr-feedback:empty{display:none}.room-rate-editor .rr-feedback[data-error=true]{color:#b42318}
    .room-rate-editor .rr-table{width:100%;margin-top:12px;border-collapse:collapse;font-size:12px;table-layout:fixed}
    .room-rate-editor .rr-table th{padding:10px 8px;background:#f8fafc;color:#667085;text-align:left;font-size:11px;font-weight:600;border-bottom:1px solid #e4eaf1}
    .room-rate-editor .rr-table td{padding:13px 8px;border-bottom:1px solid #edf0f4;vertical-align:top;overflow-wrap:anywhere}
    .room-rate-editor .rr-table tr:last-child td{border-bottom:0}.room-rate-editor .rr-table .rr-money{text-align:right;font-variant-numeric:tabular-nums;font-weight:650}
    .room-rate-editor .rr-row-actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.room-rate-editor .rr-period{display:block;line-height:1.6}
    .room-rate-editor .rr-tag{display:inline-block;padding:3px 7px;border-radius:6px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-weight:600}
    .room-rate-editor .rr-rule-name{display:block;margin-top:5px;color:#667085;font-size:11px}.room-rate-editor .rr-preview{display:inline-block;margin-top:7px;font-size:11px;color:#2563eb;text-decoration:none}
    @media(max-width:640px){.room-rate-editor .rr-fields{grid-template-columns:1fr}.room-rate-editor .rr-card{padding:13px}.room-rate-editor .rr-base{align-items:flex-start}.room-rate-editor .rr-base output{max-width:50%;font-size:13px}.room-rate-editor .rr-table thead{display:none}.room-rate-editor .rr-table,.room-rate-editor .rr-table tbody,.room-rate-editor .rr-table tr,.room-rate-editor .rr-table td{display:block;width:100%;box-sizing:border-box}.room-rate-editor .rr-table tr{padding:10px 0;border-bottom:1px solid #e4eaf1}.room-rate-editor .rr-table td{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border:0}.room-rate-editor .rr-table td:before{content:attr(data-label);flex:0 0 86px;color:#667085;font-weight:500;text-align:left}.room-rate-editor .rr-table .rr-money{text-align:right}.room-rate-editor .rr-table td.rr-empty{display:block}.room-rate-editor .rr-table td.rr-empty:before{display:none}.room-rate-editor .rr-row-actions{flex:1}}
</style>
