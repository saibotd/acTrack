<?php

require 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use saibotd\acTrack\AcTrackApplication;

unlink('.tick');
unlink('.seconds');
exec("php ticker.php >/dev/null 2>&1 &");
$application = new AcTrackApplication('acTrack', '@git-commit-short@');
$application->run();
