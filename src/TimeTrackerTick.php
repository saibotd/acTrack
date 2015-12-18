<?php
namespace saibotd\acTrack;
use saibotd\acTrack\TimeTrackerInterface;

class TimeTrackerTick implements TimeTrackerInterface{
    private $secondsPassed = 0, $isTracking = false;

    public function start(){
        $this->setSeconds($this->secondsPassed);
        $this->isTracking = true;
    }
    public function stop(){
        $secondsPassed = $this->getSeconds();
        $this->secondsPassed = 0;
        $this->isTracking = false;
        unlink('.seconds');
        return $secondsPassed;
    }
    public function pause(){
        $this->secondsPassed = $this->getSeconds();
        $this->isTracking = false;
        unlink('.seconds');
    }
    public function isPaused(){
        return !$this->isTracking;
    }
    public function getSeconds(){
        if(!$this->isTracking) return $this->secondsPassed;
        return file_get_contents('.seconds');
    }
    public function setSeconds($secondsPassed){
        $this->secondsPassed = $secondsPassed;
        file_put_contents('.seconds', $secondsPassed);
    }
}