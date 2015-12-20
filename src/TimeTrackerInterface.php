<?php

namespace saibotd\actrack;

interface TimeTrackerInterface{
    public function stop();
    public function pause();
    public function isPaused();
    public function getSeconds();
    public function setSeconds($secondsPassed);
}