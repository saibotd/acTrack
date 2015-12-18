<?php
namespace saibotd\acTrack;
use saibotd\acTrack\TimeTrackerInterface;

class TimeTrackerDiff implements TimeTrackerInterface{
    private $startedAt, $secondsPassed, $isTracking;

    public function start(){
        $this->startedAt = time();
        $this->isTracking = true;
    }
    public function stop(){
        $secondsPassed = $this->getSeconds();
        $this->startedAt = null;
        $this->secondsPassed = 0;
        $this->isTracking = false;
        return $secondsPassed;
    }
    public function pause(){
        $this->secondsPassed = $this->getSeconds();
        $this->startedAt = null;
        $this->isTracking = false;
    }
    public function isPaused(){
        return !$this->isTracking;
    }
    public function getSeconds(){
        if($this->isTracking)
            return $this->secondsPassed + (time() - $this->startedAt);
        return $this->secondsPassed;
    }
    public function setSeconds($secondsPassed){
        $this->secondsPassed = $secondsPassed;
    }
}