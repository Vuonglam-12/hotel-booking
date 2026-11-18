<?php
// Local JPEG preparation only. No Laravel bootstrap, network, database or upload.
declare(strict_types=1);
$work = realpath($argv[1] ?? '');
$allowed = 'C:\\Users\\ASUS\\AppData\\Local\\AUREL\\content-review\\';
if (!$work || !str_starts_with(str_replace('/', '\\', $work), $allowed)
    || !extension_loaded('gd') || !extension_loaded('exif')) throw new RuntimeException('Explicit private review directory and GD/EXIF required.');
$sourceRoot = realpath($work.'/images/source');
$outputRoot = realpath($work.'/images/optimized');
if (!$sourceRoot || !$outputRoot) throw new RuntimeException('Missing private image directories.');
$records = json_decode(file_get_contents($work.'/evidence/photo-rights-review.json'), true, 512, JSON_THROW_ON_ERROR);
$previousFile = $work.'/evidence/image-optimization.json';
$previous = is_file($previousFile) ? json_decode(file_get_contents($previousFile), true, 512, JSON_THROW_ON_ERROR) : [];
$results = [];
foreach ($records as $record) {
    if ($record['status'] !== 'downloaded_license_checked') continue;
    $id = (int)$record['attraction_id'];
    if (!in_array($id, [1,2,3,4,5,6,9,10,11,12,13,14,15,18,19,20,21,22,23,24,27], true)
        || !empty($record['source_review_warning'])) throw new RuntimeException('Unapproved source entered image preparation.');
    $source = realpath($record['source_file']);
    if (!$source || !str_starts_with($source, $sourceRoot.DIRECTORY_SEPARATOR)
        || hash_file('sha256', $source) !== $record['source_sha256']) throw new RuntimeException('Source path/hash mismatch.');
    $output = $outputRoot.'/'.sprintf('%02d.jpg', $id);
    $existing = array_values(array_filter($previous, fn($row) => $row['attraction_id'] === $id));
    if (is_file($output)) {
        if (count($existing) !== 1 || hash_file('sha256', $output) !== $existing[0]['sha256']) throw new RuntimeException('Unexpected existing optimized image.');
        $results[] = $existing[0]; continue;
    }
    $size = getimagesize($source);
    if (!$size || $size['mime'] !== 'image/jpeg' || $size[0]*$size[1] > 40000000) throw new RuntimeException('Invalid or oversized JPEG.');
    $image = imagecreatefromjpeg($source);
    $exif = @exif_read_data($source);
    $orientation = (int)($exif['Orientation'] ?? 1);
    if (in_array($orientation, [5,7], true)) throw new RuntimeException('Unusual EXIF orientation requires manual review.');
    if ($orientation === 2) imageflip($image, IMG_FLIP_HORIZONTAL);
    if ($orientation === 4) imageflip($image, IMG_FLIP_VERTICAL);
    if (in_array($orientation, [3,6,8], true)) {
        $degrees = [3=>180, 6=>270, 8=>90][$orientation];
        $rotated = imagerotate($image, $degrees, 0); imagedestroy($image); $image = $rotated;
    }
    $width = imagesx($image); $height = imagesy($image);
    $scale = min(1, 1280/max($width,$height));
    $newWidth = max(1,(int)round($width*$scale)); $newHeight = max(1,(int)round($height*$scale));
    $optimized = imagecreatetruecolor($newWidth,$newHeight);
    imagecopyresampled($optimized,$image,0,0,0,0,$newWidth,$newHeight,$width,$height);
    imageinterlace($optimized,true);
    $quality = 84;
    do {
        if (!imagejpeg($optimized,$output,$quality)) throw new RuntimeException('JPEG save failed.');
        clearstatcache(true,$output);
        if (filesize($output) <= 500*1024) break;
        $quality -= 6;
    } while ($quality >= 60);
    imagedestroy($optimized); imagedestroy($image);
    if (filesize($output) > 500*1024) throw new RuntimeException('JPEG target size not achieved.');
    $decoded = getimagesize($output);
    if (!$decoded || $decoded['mime'] !== 'image/jpeg') throw new RuntimeException('Prepared image cannot be decoded.');
    $results[] = ['attraction_id'=>$id,'file'=>$output,'relative_file'=>'../images/optimized/'.sprintf('%02d.jpg',$id),
        'width'=>$decoded[0],'height'=>$decoded[1],'bytes'=>filesize($output),'quality'=>$quality,
        'progressive'=>true,'upscaled'=>false,'permanent_crop'=>false,'orientation_applied'=>$orientation,
        'source_sha256'=>$record['source_sha256'],'sha256'=>hash_file('sha256',$output),
        'changes'=>'Resized to a maximum edge of 1280px without upscaling; re-encoded as progressive JPEG; EXIF removed; no permanent crop or generated content.'];
}
file_put_contents($previousFile,json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");
echo json_encode(['prepared'=>count($results),'bytes'=>array_sum(array_column($results,'bytes')),'max_bytes'=>$results ? max(array_column($results,'bytes')) : 0,'database_writes'=>0],JSON_THROW_ON_ERROR)."\n";
