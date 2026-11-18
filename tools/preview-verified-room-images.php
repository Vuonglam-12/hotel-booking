<?php
// Private contact sheets for checking downloaded source photographs. Originals remain unchanged.
$root=dirname(__DIR__);$work=$root.'/storage/app/private/room-type-enrichment-20261031';
$images=array_values(array_filter(json_decode(file_get_contents($work.'/downloaded-images.json'),true),fn($i)=>isset($i['sha256'])));
foreach(array_chunk($images,35) as $page=>$entries){
    $canvas=imagecreatetruecolor(1250,7*160);$white=imagecolorallocate($canvas,255,255,255);$black=imagecolorallocate($canvas,0,0,0);imagefill($canvas,0,0,$white);
    foreach($entries as $i=>$entry){$photo=imagecreatefromstring(file_get_contents($root.'/public/'.$entry['local_path']));$x=($i%5)*250;$y=intdiv($i,5)*160;
        imagecopyresampled($canvas,$photo,$x,$y,0,0,245,125,imagesx($photo),imagesy($photo));
        imagestring($canvas,2,$x+3,$y+128,substr($entry['key'],0,35),$black);imagedestroy($photo);
    }
    imagepng($canvas,$work.'/image-review-'.($page+1).'.png');imagedestroy($canvas);
}
echo count($images),' source photographs in private contact sheets.',PHP_EOL;
