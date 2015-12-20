<?php
namespace saibotd\actrack;
use saibotd\actrack\TimeTrackerInterface;

class TimeTrackerTick implements TimeTrackerInterface{
    private $secondsPassed = 0, $isTracking = false, $testFile, $saveFile;

    public function __construct(){
        $this->launchTicker();
    }

    public function __destruct(){
        $this->killTicker();
    }

    public function killTicker(){
        unlink($this->testFile);
        unlink($this->saveFile);
    }

    public function launchTicker(){
        $this->testFile = tempnam(sys_get_temp_dir(), "ticker_test");
        $this->saveFile = tempnam(sys_get_temp_dir(), "ticker_seconds");
        exec("php ticker.php '$this->testFile' '$this->saveFile' >/dev/null 2>&1 &");
        usleep(10000); //Wait for the "daemon" to launch
    }

    public function isWorking(){
        return $this->testFile && $this->saveFile && file_get_contents($this->testFile) == "OK";
    }

    public function start(){
        $this->setSeconds($this->secondsPassed);
        $this->isTracking = true;
    }
    public function stop(){
        $secondsPassed = $this->getSeconds();
        $this->secondsPassed = 0;
        $this->isTracking = false;
        unlink($this->saveFile);
        return $secondsPassed;
    }
    public function pause(){
        $this->secondsPassed = $this->getSeconds();
        $this->isTracking = false;
        unlink($this->saveFile);
    }
    public function isPaused(){
        return !$this->isTracking;
    }
    public function getSeconds(){
        if(!$this->isTracking) return $this->secondsPassed;
        return file_get_contents($this->saveFile);
    }
    public function setSeconds($secondsPassed){
        $this->secondsPassed = $secondsPassed;
        file_put_contents($this->saveFile, $secondsPassed);
    }
}