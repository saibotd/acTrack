<?php

require 'vendor/autoload.php';
require 'src/Helper.php';
require 'src/ACTimeTrackCommand.php';

use Symfony\Component\Console\Application;

$application = new Application("acTimetrack");
$command = new ACTimeTrackCommand();
$application->add($command);
$application->setDefaultCommand($command->getName());
$application->run();
