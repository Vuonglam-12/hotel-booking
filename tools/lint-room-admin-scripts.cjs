// Syntax check only: compiles script text without executing UI code or making requests.
const fs=require('node:fs'),vm=require('node:vm');
const files=['resources/views/admin/partials/room-type-validation-assets.blade.php','resources/views/admin/hotel-detail.blade.php','resources/views/admin/partials/room-type-modal-script.blade.php','resources/views/admin/partials/room-rate-editor-script.blade.php'];
let count=0;
for(const file of files){
    const source=fs.readFileSync(file,'utf8');
    for(const match of source.matchAll(/<script>([\s\S]*?)<\/script>/g)){
        const script=match[1].replace(/=\s*@js[^\n]*;/g,'= null;');
        new vm.Script(script,{filename:file});count++;
    }
}
console.log(JSON.stringify({views:files.length,scripts_parsed:count,executed:false}));
