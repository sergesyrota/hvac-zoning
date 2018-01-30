<?php

function getCurrentTemp($unit='F') {
    $temp = 79;
    if ($unit == 'C') {
        return FtoC($temp);
    }
    return $temp;
}
