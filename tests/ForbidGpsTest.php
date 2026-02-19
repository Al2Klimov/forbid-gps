<?php

// Compatibility shim for PHPUnit 5.x which uses underscore-separated class names
if (!class_exists('PHPUnit\Framework\TestCase') && class_exists('PHPUnit_Framework_TestCase')) {
    class_alias('PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}

class ForbidGpsTest extends PHPUnit\Framework\TestCase
{
    // Compatibility wrapper: assertStringContainsString was added in PHPUnit 7.5;
    // fall back to strpos + assertTrue for older versions (assertContains was
    // deprecated for strings in PHPUnit 8 and removed in PHPUnit 9).
    private function assertStringContains($needle, $haystack, $message = '')
    {
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString($needle, $haystack, $message);
        } else {
            $this->assertTrue(
                strpos($haystack, $needle) !== false,
                $message !== '' ? $message : "Failed asserting that '$haystack' contains '$needle'."
            );
        }
    }

    public function testRemovePrefixStripsGPS()
    {
        $this->assertEquals('Latitude', forbid_gps_remove_prefix('GPSLatitude'));
    }

    public function testRemovePrefixLeavesNonGPS()
    {
        $this->assertEquals('Make', forbid_gps_remove_prefix('Make'));
    }

    public function testNonImageFilePassesThrough()
    {
        $file = array('name' => 'doc.pdf', 'type' => 'application/pdf', 'tmp_name' => '', 'size' => 0);
        $result = forbid_gps_handle_upload_prefilter($file);
        // Non-image files must pass through completely unchanged
        $this->assertEquals($file, $result);
    }

    public function testImageWithFalseExifPassesThrough()
    {
        $file = array(
            'name'     => 'photo.png',
            'type'     => 'image/png',
            'tmp_name' => __DIR__ . '/fixtures/image-no-exif.png',
            'size'     => 0,
        );
        // exif_read_data() emits a PHP warning for non-JPEG/TIFF files; suppress it
        // because the "returns false for PNG" path is the behaviour under test here.
        set_error_handler(function () { return true; }, E_WARNING);
        $result = forbid_gps_handle_upload_prefilter($file);
        restore_error_handler();
        // PNG returns false from exif_read_data; must pass through unchanged
        $this->assertEquals($file, $result);
    }

    public function testImageWithoutGpsKeysPassesThrough()
    {
        $file = array(
            'name'     => 'photo.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => __DIR__ . '/fixtures/image-without-gps.jpg',
            'size'     => 0,
        );
        $result = forbid_gps_handle_upload_prefilter($file);
        // EXIF without GPS keys must pass through unchanged
        $this->assertEquals($file, $result);
    }

    public function testImageWithGpsKeysReturnsError()
    {
        $file = array(
            'name'     => 'photo.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => __DIR__ . '/fixtures/image-with-gps.jpg',
            'error'    => 0,
            'size'     => 0,
        );
        $result = forbid_gps_handle_upload_prefilter($file);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContains('Latitude', $result['error']);
        $this->assertStringContains('Longitude', $result['error']);
    }
}
