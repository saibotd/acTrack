<?php
if(!is_file('vendor/autoload.php')) die("Composer autoloader missing. Did you install the modules?\n");

require 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use saibotd\actrack\AcTrackApplication;

$application = new AcTrackApplication('acTrack', '@git-commit-short@');
$application->run();
