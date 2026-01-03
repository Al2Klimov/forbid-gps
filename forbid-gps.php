<?php

/*
 * Plugin Name:         Forbid GPS
 * Plugin URI:          https://github.com/Al2Klimov/forbid-gps
 * Description:         Prevents photos containing geographic location data from being uploaded.
 * Version:             1.0
 * Requires at least:   2.9
 * Requires PHP:        5.4
 * Author:              Alexander A. Klimov
 * Author URI:          https://github.com/Al2Klimov
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         forbid-gps
 */

// Notes on the requirements above:
//
// Auto-formatting forces [] by default which requires PHP 5.4.
// Unless commented otherwise, the used functions require PHP 4.x or even 3.x.
// __ requires WordPress 2.1.

function forbid_gps_handle_upload_prefilter($file)
{
    if (strpos($file["type"], "image/") !== 0) {
        return $file;
    }

    if (!function_exists("exif_read_data")) {
        $file["error"] = __(
            "Image may contain GPS data, can't inspect it, missing the exif extension for PHP",
            "forbid-gps",
        );

        return $file;
    }

    $exif = exif_read_data($file["tmp_name"]);

    if ($exif === false) {
        // Can happen with e.g. PNG
        return $file;
    }

    $blacklist = [
        "GPSAltitude",
        "GPSAltitudeRef",
        "GPSImgDirection",
        "GPSImgDirectionRef",
        "GPSLatitude",
        "GPSLatitudeRef",
        "GPSLongitude",
        "GPSLongitudeRef",
    ];

    $forbidden = array_intersect($blacklist, array_keys($exif));

    if (!empty($forbidden)) {
        $file["error"] = sprintf(
            __("Image contains GPS data: %s", "forbid-gps"),
            implode(" ", $forbidden),
        );
    }

    return $file;
}

// wp_handle_upload_prefilter requires WordPress 2.9
add_filter("wp_handle_upload_prefilter", "forbid_gps_handle_upload_prefilter");
