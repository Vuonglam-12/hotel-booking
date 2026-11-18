const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const root=path.resolve(__dirname,'../..');
const read=file=>fs.readFileSync(path.join(root,file),'utf8');

test('Active Admin room-type screens reuse the shared gallery editor',()=>{
    const detail=read('resources/views/admin/hotel-detail.blade.php');
    const modal=read('resources/views/admin/partials/room-type-modal.blade.php');
    assert.ok(detail.includes("'editorId' => 'hotel-room-images'"));
    assert.ok(modal.includes("'editorId' => 'modal-room-images'"));
    assert.ok(!detail.includes('id="roomImageFile"'));
    assert.ok(!detail.includes('roomImageFiles'));
});

test('Image changes are submitted with the room form through the shared contract',()=>{
    const detail=read('resources/views/admin/hotel-detail.blade.php');
    const modal=read('resources/views/admin/partials/room-type-modal-script.blade.php');
    const hotels=read('resources/views/admin/hotels.blade.php');
    assert.ok(detail.includes("roomImages.body(payload, isNew ? 'POST' : 'PUT')"));
    assert.ok(modal.includes('modalRoomImages.body(payload,method)'));
    assert.ok(detail.includes('!roomImages.ready'));
    assert.ok(modal.includes('!modalRoomImages.ready'));
    assert.ok(hotels.includes("'editorId' => 'hotel-images'"));
    assert.ok(detail.includes("'editorId' => 'hotel-detail-images'"));
});
