<?php

require 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use saibotd\acTrack\ACTimeTrackCommand;

$application = new Application('acTrack', '@git-commit-short@');
$command = new ACTimeTrackCommand();
$application->add($command);
$application->setDefaultCommand($command->getName());
$application->run();
