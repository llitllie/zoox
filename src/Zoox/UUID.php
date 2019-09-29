<?php

declare(strict_types=1);

namespace Zoox;

class UUID
{
    /**
     * may also add something like server id, client ip, url parameters
     * Generate v4 UUID
     * https://gist.github.com/dahnielson/508447
     * Version 4 UUIDs are pseudo-random.
     */
    public static function v4()
    {
        return \sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            \random_int(0, 0xffff), \random_int(0, 0xffff),
            // 16 bits for "time_mid"
            \random_int(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            \random_int(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            \random_int(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            \random_int(0, 0xffff), \random_int(0, 0xffff), \random_int(0, 0xffff)
            );
    }

    public static function generate()
    {
        return self::v4();
        //$uudi = self::v4();
        //return str_replace('-', '', $uudi);
    }
}
