<?php

require 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use saibotd\acTrack\AcTrackApplication;

$application = new AcTrackApplication('acTrack', '@git-commit-short@');
$application->run();
