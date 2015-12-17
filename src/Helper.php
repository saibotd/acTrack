<?php
namespace saibotd\acTrack;

class Helper{
    public static function format_time($t,$f=':') {
        return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
    }
}