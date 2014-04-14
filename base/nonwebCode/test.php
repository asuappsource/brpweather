<?php

echo '0deg = '.convertCardinal(0)."\n";
echo '90deg = '.convertCardinal(90)."\n";
echo '180deg = '.convertCardinal(180)."\n";
echo '270deg = '.convertCardinal(270)."\n";
echo '360deg = '.convertCardinal(360)."\n";
echo 'NNE = '.convertCardinal('NNE')."\n";
echo 'SWbS = '.convertCardinal('SWbS')."\n";
echo 'ese = '.convertCardinal('ese')."\n";
echo 'S = '.convertCardinal('S')."\n";
echo 'q = '.convertCardinal('q')."\n";

function test() {
    $inotify = inotify_init();
    
    $wd1 = inotify_add_watch($inotify, '.', IN_ALL_EVENTS);
    $wd2 = inotify_add_watch($inotify, '.', IN_ALL_EVENTS);
    if ($wd1 === false) 
        echo "Failed wd1 \n";
    else echo "wd1 = $wd1\n";
    if ($wd2 === false) 
        echo "Failed wd2 \n";
    else echo "wd2 = $wd2\n";

    if (inotify_rm_watch($inotify, $wd1))
        echo "Removed wd1\n";
    if (@inotify_rm_watch($inotify, $wd2))
        echo "Removed wd2\n";
    else echo "Failed to remove wd2\n";

    fclose($inotify);
}

function testDate() {
    echo date('Y-m-d H:i:s', strtotime('last day of this year'));
    echo "\n";
}

function convertCardinal($dir) {
    static $map = array(
            'N'    =>      0,
            'NbE'  =>  11.25,
            'NNE'  =>  22.50,
            'NEbN' =>  33.75,
            'NE'   =>  45.00,
            'NEbE' =>  56.25,
            'ENE'  =>  67.50,
            'EbN'  =>  78.75,
            'E'    =>  90.00,
            'EbS'  => 101.25,
            'ESE'  => 112.50,
            'SEbE' => 123.75,
            'SE'   => 135.00,
            'SEbS' => 146.25,
            'SSE'  => 157.50,
            'SbE'  => 168.75,
            'S'    => 180.00,
            'SbW'  => 191.00,
            'SSW'  => 202.50,
            'SWbS' => 213.75,
            'SW'   => 225.00,
            'SWbW' => 236.25,
            'WSW'  => 247.50,
            'WbSW' => 258.75,
            'W'    => 270.00,
            'WbN'  => 281.25,
            'WNW'  => 292.50,
            'NWbW' => 303.75,
            'NW'   => 315.00,
            'NWbN' => 326.25,
            'NNW'  => 337.50,
            'NbW'  => 348.75);

    // just hardcode this, would be faster to have as static
    // $mapStrings = array_keys($map);
    static $mapStrings = array(
            'N',
            'NbE',
            'NNE',
            'NEbN',
            'NE',
            'NEbE',
            'ENE',
            'EbN',
            'E',
            'EbS',
            'ESE',
            'SEbE',
            'SE',
            'SEbS',
            'SSE',
            'SbE',
            'S',
            'SbW',
            'SSW',
            'SWbS',
            'SW',
            'SWbW',
            'WSW',
            'WbSW',
            'W',
            'WbN',
            'WNW',
            'NWbW',
            'NW',
            'NWbN',
            'NNW',
            'NbW');

    if (is_string($dir)) {
        $dir = strtoupper($dir);
        if (isset($map[$dir]))
            return $map[$dir];
    } else if (is_int($dir) || is_double($dir)) {
        $dir = floor((($dir/11.25) + 1.5) < 33 ? (($dir/11.25) + 1.5) : (($dir/11.25) + 1.5) - 32);
        if (isset($mapStrings[$dir-1]))
            return $mapStrings[$dir-1];
    }
    return -1;
}
?>
