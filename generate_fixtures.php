<?php

$dir = __DIR__ . '/tests/Fixtures';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

// Generate valid JPEG
$im = imagecreatetruecolor(100, 100);
$bg = imagecolorallocate($im, 255, 0, 0);
imagefilledrectangle($im, 0, 0, 100, 100, $bg);
imagejpeg($im, $dir . '/test.jpg');
imagedestroy($im);

// Generate valid PNG
$im = imagecreatetruecolor(100, 100);
$bg = imagecolorallocate($im, 0, 255, 0);
imagefilledrectangle($im, 0, 0, 100, 100, $bg);
imagepng($im, $dir . '/test.png');
imagedestroy($im);

// Generate an invalid file that pretends to be an image
file_put_contents($dir . '/fake_image.jpg', 'This is not an image');

echo "Fixtures generated.\n";
