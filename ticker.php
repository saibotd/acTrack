<?php

if(count($argv) !== 3) exit;
$testFile = $argv[1];
$saveFile = $argv[2];
if(!file_put_contents($testFile, "OK")) exit;
for(;;){
	if(!file_exists($testFile)) break;
	file_put_contents($saveFile, intval(file_get_contents($saveFile)) + 1);
    sleep(1);
}
