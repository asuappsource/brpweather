<?php

defined("ENT_XML1") or define("ENT_XML1", 16);

// helper function that can convert a cardinal direction string to a degree value
// or a degree value to the closest cardinal direction string.
// detects input automatically based on is_string or is_numeric
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
  if (is_int($dir) || is_double($dir)) {
    $dir = floor((($dir/11.25) + 1.5) < 33 ? (($dir/11.25) + 1.5) : (($dir/11.25) + 1.5) - 32);
    if (isset($mapStrings[$dir-1]))
      return $mapStrings[$dir-1];
  }
  else if (is_string($dir)) {
    $dir = strtoupper($dir);
    if (isset($map[$dir]))
      return $map[$dir];
  }
  return -1;
}

?>