<?php
$source = __DIR__ . '/public/assets/images/logologo.png';
$outputDir = __DIR__ . '/public/assets/images/icons/';

function resizeImage($source, $dest, $size, $maskable = false) {
    $img = imagecreatefrompng($source);
    $width = imagesx($img);
    $height = imagesy($img);

    $canvas = imagecreatetruecolor($size, $size);
    imagesavealpha($canvas, true);

    if ($maskable) {
        $bg = imagecolorallocate($canvas, 0x2C, 0x18, 0x10);
        imagefill($canvas, 0, 0, $bg);
        $padding = (int)($size * 0.1);
        $innerSize = $size - ($padding * 2);
        imagecopyresampled($canvas, $img, $padding, $padding, 0, 0, $innerSize, $innerSize, $width, $height);
    } else {
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $size, $size, $width, $height);
    }

    imagepng($canvas, $dest);
    imagedestroy($img);
    imagedestroy($canvas);
}

resizeImage($source, $outputDir . 'icon-192.png', 192);
resizeImage($source, $outputDir . 'icon-512.png', 512);
resizeImage($source, $outputDir . 'icon-512-maskable.png', 512, true);

echo "Icônes générées avec succès !\n";