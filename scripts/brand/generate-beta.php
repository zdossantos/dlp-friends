<?php

// Run explicitly with `php scripts/brand/generate-beta.php` after editing the source icons.
$root = dirname(__DIR__, 2).'/public';
@mkdir($root.'/beta/pwa', 0755, true);

$badge = '<g><rect x="290" y="386" width="198" height="102" rx="12" fill="white" stroke="#18121f" stroke-width="6"/><text x="389" y="454" text-anchor="middle" font-family="Arial, sans-serif" font-weight="bold" font-size="57" fill="#18121f">BETA</text></g>';
file_put_contents($root.'/beta/favicon.svg', str_replace('</svg>', $badge.'</svg>', file_get_contents($root.'/favicon.svg')));

function badgedIcon(string $source, int $size, bool $maskable = false): GdImage
{
    $original = imagecreatefrompng($source);
    $image = imagescale($original, 512, 512, IMG_BICUBIC_FIXED);
    imagesavealpha($image, true);
    $white = imagecolorallocate($image, 255, 255, 255);
    $ink = imagecolorallocate($image, 24, 18, 31);
    [$x, $y, $width, $height, $scale] = $maskable ? [274, 322, 120, 58, 2] : [290, 386, 198, 102, 3];
    imagefilledrectangle($image, $x, $y, $x + $width, $y + $height, $ink);
    imagefilledrectangle($image, $x + 3, $y + 3, $x + $width - 3, $y + $height - 3, $white);
    $text = imagecreatetruecolor(36, 15);
    imagefill($text, 0, 0, imagecolorallocate($text, 255, 255, 255));
    imagestring($text, 5, 0, 0, 'BETA', imagecolorallocate($text, 24, 18, 31));
    imagecopyresized($image, $text, $x + (int) (($width - 36 * $scale) / 2), $y + (int) (($height - 15 * $scale) / 2), 0, 0, 36 * $scale, 15 * $scale, 36, 15);

    return imagescale($image, $size, $size, IMG_BICUBIC_FIXED);
}

foreach ([192, 512] as $size) {
    foreach (['icon', 'icon-maskable'] as $variant) {
        imagepng(badgedIcon($root."/pwa/{$variant}-512.png", $size, $variant === 'icon-maskable'), $root."/beta/pwa/{$variant}-{$size}.png");
    }
}
imagepng(badgedIcon($root.'/apple-touch-icon.png', 180), $root.'/beta/apple-touch-icon.png');

$entries = '';
$images = '';
$offset = 6 + 3 * 16;
foreach ([16, 32, 48] as $size) {
    ob_start();
    imagepng(badgedIcon($root.'/apple-touch-icon.png', $size));
    $bytes = ob_get_clean();
    $entries .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($bytes), $offset);
    $images .= $bytes;
    $offset += strlen($bytes);
}
file_put_contents($root.'/beta/favicon.ico', pack('vvv', 0, 1, 3).$entries.$images);

$manifest = json_decode(file_get_contents($root.'/app.webmanifest'), true, flags: JSON_THROW_ON_ERROR);
$brand = require dirname(__DIR__, 2).'/lang/fr/brand.php';
$manifest['id'] = '/beta';
$manifest['name'] = $manifest['short_name'] = $brand['beta_app_name'];
foreach ($manifest['icons'] as &$icon) {
    $icon['src'] = '/beta'.$icon['src'];
}
unset($icon);
file_put_contents($root.'/beta/app.webmanifest', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
