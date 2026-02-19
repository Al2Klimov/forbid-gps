<?php
/**
 * Generate real binary fixture images for ForbidGps tests.
 * Usage: php tests/create-fixtures.php
 */

$dir = __DIR__ . '/fixtures';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Helper: pack a little-endian 16-bit integer
function le16($v) { return pack('v', $v); }
// Helper: pack a little-endian 32-bit integer
function le32($v) { return pack('V', $v); }
// Helper: pack a TIFF RATIONAL value (two 32-bit unsigned integers)
function rational($num, $den) { return le32($num) . le32($den); }
// Helper: build a 12-byte TIFF IFD entry
function ifdEntry($tag, $type, $count, $valueOrOffset)
{
    return le16($tag) . le16($type) . le32($count) . $valueOrOffset;
}

/**
 * Build a minimal JPEG containing an APP1/EXIF segment with GPS tags.
 *
 * TIFF layout (little-endian, offsets from TIFF start):
 *   0  : TIFF header (8 bytes)
 *   8  : IFD0 — 1 entry (GPSInfo pointer) + next-IFD (18 bytes total)
 *   26 : GPS IFD — 4 entries + next-IFD (54 bytes total)
 *   80 : GPSLatitude data  — 3 rationals (24 bytes)
 *   104: GPSLongitude data — 3 rationals (24 bytes)
 */
function buildJpegWithGps()
{
    $gpsIfdOffset = 26;
    $latDataOffset = 80;
    $lonDataOffset = 104;

    // TIFF header: "II" (little-endian), magic 42, IFD0 at offset 8
    $tiff  = 'II' . le16(42) . le32(8);

    // IFD0: 1 entry — GPSInfo (tag 0x8825, type LONG, count 1, value=GPS IFD offset)
    $tiff .= le16(1);
    $tiff .= ifdEntry(0x8825, 4, 1, le32($gpsIfdOffset));
    $tiff .= le32(0); // next IFD = none

    // GPS IFD: 4 entries
    $tiff .= le16(4);
    // GPSLatitudeRef  (0x0001): ASCII, 2 bytes, value "N\0" fits in 4 bytes
    $tiff .= ifdEntry(0x0001, 2, 2, "N\x00\x00\x00");
    // GPSLatitude     (0x0002): RATIONAL, 3 values, data at $latDataOffset
    $tiff .= ifdEntry(0x0002, 5, 3, le32($latDataOffset));
    // GPSLongitudeRef (0x0003): ASCII, 2 bytes, value "E\0" fits in 4 bytes
    $tiff .= ifdEntry(0x0003, 2, 2, "E\x00\x00\x00");
    // GPSLongitude    (0x0004): RATIONAL, 3 values, data at $lonDataOffset
    $tiff .= ifdEntry(0x0004, 5, 3, le32($lonDataOffset));
    $tiff .= le32(0); // next IFD = none

    // Data: GPSLatitude = 51°30'0" N
    $tiff .= rational(51, 1) . rational(30, 1) . rational(0, 1);
    // Data: GPSLongitude = 0°7'30" E
    $tiff .= rational(0, 1) . rational(7, 1) . rational(30, 1);

    return wrapInJpeg($tiff);
}

/**
 * Build a minimal JPEG containing an APP1/EXIF segment with a Make tag but no GPS.
 *
 * TIFF layout:
 *   0  : TIFF header (8 bytes)
 *   8  : IFD0 — 1 entry (Make) + next-IFD (18 bytes total)
 *   26 : Make string data ("TestCamera\0", 11 bytes)
 */
function buildJpegWithoutGps()
{
    $makeValue  = "TestCamera\x00"; // 11 bytes
    $makeOffset = 26;               // right after IFD0

    // TIFF header
    $tiff  = 'II' . le16(42) . le32(8);

    // IFD0: 1 entry — Make (tag 0x010F, type ASCII)
    $tiff .= le16(1);
    $tiff .= ifdEntry(0x010F, 2, strlen($makeValue), le32($makeOffset));
    $tiff .= le32(0); // next IFD = none

    // Data: Make string
    $tiff .= $makeValue;

    return wrapInJpeg($tiff);
}

/**
 * Wrap TIFF data in a real JPEG (SOI + APP1 + GD image body).
 * Injects the EXIF APP1 segment right after the SOI marker of a GD-generated
 * 1×1 JPEG so that PHP's exif_read_data sees both valid EXIF and a proper image.
 */
function wrapInJpeg($tiffData)
{
    $exifData = "Exif\x00\x00" . $tiffData;
    // APP1 length field is big-endian and includes the 2-byte length field itself
    $app1Len  = pack('n', strlen($exifData) + 2);
    $app1     = "\xFF\xE1" . $app1Len . $exifData;

    // Generate a real 1×1 JPEG via GD for a valid image body
    $img = imagecreatetruecolor(1, 1);
    imagefilledrectangle($img, 0, 0, 0, 0, imagecolorallocate($img, 255, 255, 255));
    ob_start();
    imagejpeg($img, null, 75);
    $gdJpeg = ob_get_clean();
    imagedestroy($img);

    // $gdJpeg starts with SOI (FF D8); insert APP1 right after it
    return substr($gdJpeg, 0, 2) . $app1 . substr($gdJpeg, 2);
}

// Build and write the GPS JPEG
file_put_contents($dir . '/image-with-gps.jpg', buildJpegWithGps());
echo "Created image-with-gps.jpg\n";

// Build and write the non-GPS JPEG
file_put_contents($dir . '/image-without-gps.jpg', buildJpegWithoutGps());
echo "Created image-without-gps.jpg\n";

// Build and write the PNG (no EXIF — exif_read_data returns false for PNGs)
// Minimal 1×1 white PNG generated via GD or hard-coded if GD is unavailable
if (function_exists('imagepng')) {
    $img = imagecreatetruecolor(1, 1);
    imagefilledrectangle($img, 0, 0, 0, 0, imagecolorallocate($img, 255, 255, 255));
    imagepng($img, $dir . '/image-no-exif.png');
    imagedestroy($img);
} else {
    // Minimal valid 1×1 white PNG (hard-coded fallback)
    file_put_contents($dir . '/image-no-exif.png', base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAAAAAA6fptVAAAACklEQVQI12NgAAAAAgAB4iG8MwAAAABJRU5ErkJggg=='
    ));
}
echo "Created image-no-exif.png\n";

// Verify that exif_read_data returns the expected data
echo "\nVerifying fixtures...\n";

$gpsExif = @exif_read_data($dir . '/image-with-gps.jpg');
if ($gpsExif === false) {
    echo "ERROR: exif_read_data returned false for image-with-gps.jpg\n";
    exit(1);
}
$gpsKeys = array_intersect(
    array_keys($gpsExif),
    array('GPSLatitude', 'GPSLatitudeRef', 'GPSLongitude', 'GPSLongitudeRef')
);
if (count($gpsKeys) < 4) {
    echo "ERROR: image-with-gps.jpg is missing expected GPS keys. Found: " . implode(', ', array_keys($gpsExif)) . "\n";
    exit(1);
}
echo "image-with-gps.jpg OK — GPS keys: " . implode(', ', array_keys($gpsExif)) . "\n";

$noGpsExif = @exif_read_data($dir . '/image-without-gps.jpg');
if ($noGpsExif === false) {
    echo "ERROR: exif_read_data returned false for image-without-gps.jpg\n";
    exit(1);
}
$forbiddenKeys = array('GPSLatitude','GPSLatitudeRef','GPSLongitude','GPSLongitudeRef',
                       'GPSAltitude','GPSAltitudeRef','GPSImgDirection','GPSImgDirectionRef');
$foundForbidden = array_intersect(array_keys($noGpsExif), $forbiddenKeys);
if (!empty($foundForbidden)) {
    echo "ERROR: image-without-gps.jpg contains GPS keys: " . implode(', ', $foundForbidden) . "\n";
    exit(1);
}
echo "image-without-gps.jpg OK — EXIF keys: " . implode(', ', array_keys($noGpsExif)) . "\n";

$pngExif = @exif_read_data($dir . '/image-no-exif.png');
if ($pngExif !== false) {
    echo "ERROR: exif_read_data did not return false for image-no-exif.png\n";
    exit(1);
}
echo "image-no-exif.png OK — exif_read_data returned false\n";

echo "\nAll fixtures verified successfully.\n";
