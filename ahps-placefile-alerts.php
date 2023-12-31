<?php
#---------------------------------------------------------------------------
/*
Program: ahps-placefile-alerts.php

Purpose: generate a GRLevelX placefile to display AHPS guages with alert conditions

Usage:   invoke as a placefile in the GrlevelX placefile manager

Requires: decoded gauge data in ahps-levels-inc-inc.php produced by get-ahps-levels.php
          
Author: Ken True - webmaster@saratoga-weather.org

Acknowledgement:
  
   Special thanks to Mike Davis, W1ARN of the National Weather Service, Nashville TN office
	 for his testing/feedback during development.   

Version 1.00 - 24-Jul-2023 - initial release
*/
#---------------------------------------------------------------------------

#-----------settings--------------------------------------------------------
date_default_timezone_set('UTC');
$timeFormat = "d-M-Y g:ia T";  // time display for date() in popup
#-----------end of settings-------------------------------------------------

$Version = "ahps-placefile-alerts.php V1.00 - 24-Jul-2023 - webmaster@saratoga-weather.org";
global $Version,$timeFormat;

// self downloader
if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
   //--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   
   readfile($filenameReal);
   exit;
}

header('Content-type: text/plain,charset=ISO-8859-1');

if(file_exists("ahps-levels-inc.php")) {
	include_once("ahps-levels-inc.php");
} else {
	print "Warning: ahps-levels-inc.php file not found. Aborting.\n";
	exit;
}

if(isset($_GET['lat'])) {$latitude = $_GET['lat'];}
if(isset($_GET['lon'])) {$longitude = $_GET['lon'];}
if(isset($_GET['version'])) {$version = $_GET['version'];}

if(isset($latitude) and !is_numeric($latitude)) {
	print "Bad latitude spec.";
	exit;
}
if(isset($latitude) and $latitude >= -90.0 and $latitude <= 90.0) {
	# OK latitude
} else {
	print "Latitude outside range -90.0 to +90.0\n";
	exit;
}

if(isset($longitude) and !is_numeric($longitude)) {
	print "Bad longitude spec.";
	exit;
}
if(isset($longitude) and $longitude >= -180.0 and $longitude <= 180.0) {
	# OK longitude
} else {
	print "Longitude outside range -180.0 to +180.0\n";
	exit;
}	
if(!isset($latitude) or !isset($longitude) or !isset($version)) {
	print "This script only runs via a GRlevelX placefile manager.";
	exit();
}

/*
Sample entry annotated:

$AHPS = array (
  'AAIT2' => '30.221111|-97.793333|Williamson Creek at Manchaca Road at Austin|TX|no_flooding|2023-07-24 14:30:00|ft|1.99|||0.00|-999.00|13.00|14.00|18.00',
  'AAMC1' => '37.771667|-122.298333|San Francisco Bay at Alameda tide gage|CA|not_defined|2023-07-24 14:42:00|ft|3.14||||-999.00|||',
  'AANG1' => '33.820306|-84.407639|Peachtree Creek at Atlanta|GA|no_flooding|2023-07-24 14:00:00|ft|2.52|13.00||0.00|0.05|17.00|18.00|20.00',
  ),
*/
  static $StatusLookup = array (
  'out_of_service'  => array('icon'=>1,'legend'=>'Out of Service'),
  'obs_not_current' => array('icon'=>2,'legend'=>'Observations Are Not Current'),
  'low_threshold'   => array('icon'=>3,'legend'=>'At or Below Low Water Threshold'),
  'not_defined'     => array('icon'=>4,'legend'=>'Flood Category Not Defined'),
  'no_flooding'     => array('icon'=>5,'legend'=>'No Flooding'),
  'action'          => array('icon'=>6,'legend'=>'Near Flood'),
  'minor'           => array('icon'=>7,'legend'=>'Minor Flooding'),
	'moderate'        => array('icon'=>8,'legend'=>'Moderate Flooding'),
  'major'           => array('icon'=>9,'legend'=>'Major Flooding'),
  );

#---------------------------------------------------------------------------
#  main program
#---------------------------------------------------------------------------

gen_header(); # emit the placefile header contents

foreach ($AHPS as $ID => $rec) {
	
	$M = explode('|',$rec);
	
  if(!isset($M[0]) or !isset($M[1])) {
		#print "; -- missing LATITUDE and/or LONGITUDE .. ignored.\n";
		continue;
	}
	list($miles,$km,$bearingDeg,$bearingWR) = 
	  GML_distance((float)$latitude, (float)$longitude,(float)$M[0], (float)$M[1]);
	$icon = $StatusLookup[$M[4]]['icon'];
	if($icon >= 6 or $icon == 3) { # action/minor/moderate/major or low only
		#print "..$ICAO is $miles $bearingWR\n";
		gen_entry($ID,$M,$miles,$bearingWR);  # generate icon and popup
	}
}

#---------------------------------------------------------------------------
# functions
#---------------------------------------------------------------------------
function gen_header() {
	global $Version;
	$title = "AHPS Alert Observations";
	print '; placefile generated by '.$Version. '
; Generated on '.gmdate('r').'
;
Title: '.$title.' - '.gmdate('r').' 
Refresh: 7
Color: 255 255 255
Font: 1, 12, 1, Arial
IconFile: 1, 20, 20, 10, 10, "ahps-icons2-sm.png"
Threshold: 999

';
	
}

#---------------------------------------------------------------------------

function gen_entry($ID,$M,$miles,$bearingWR) {
	global $StatusLookup;
/*
  Purpose: generate the detail entry with popup for the AHPS report
	from ahps-levels-inc.php $AHPS array produced by get-ahps-levels.php<br />
  and encoded using:
	
	$M[0] = $RA['latitude'];
	$M[1] = $RA['longitude'];
	$M[2] = $RA['waterbody'] . ' at '. $RA['location'];
	$M[3] = $RA['state'];
	$M[4] = $RA['status'];
	$M[5] = $RA['obstime'];
	$M[6] = $RA['units'];
	$M[7] = $RA['observed'];
	$M[8] = $RA['action'];
	$M[9] = $RA['forecast'];
	$M[10] = $RA['lowthresh'];
	$M[11 = $RA['secvalue'];
	$M[12] = $RA['flood'];
	$M[13] = $RA['moderate'];
	$M[14] = $RA['major'];

*/	

  print "; generate ".$ID." ".$M[2]." at ".$M[0].','.$M[1]." at $miles miles $bearingWR \n";
	
  $output = 'Object: '.$M[0].','.$M[1]. "\n";
  $output .= "Threshold: 999\n";
	$icon = $StatusLookup[$M[4]]['icon'];
	if($icon < 0) {$icon = 0; } # show missing icon if not found
  $output .= "Icon: 0,0,000,1,$icon,\"".gen_popup($ID,$M)."\"\n";
  $output .= "End:\n\n";

  print $output;	
	
}
#---------------------------------------------------------------------------

function gen_popup($ID,$M) {
	global $timeFormat,$StatusLookup;
	# note use '\n' to end each line so GRLevelX will do a new-line in the popup.

	$out = "ID: $ID ".$M[2]."(".$M[3].')\n   ('.$M[0].",".$M[1].')\n';
	$out .= "----------------------------------------------------------".'\n';

	$obsTime = strtotime($M[5].' UTC');
	$out .= "Time: ".date($timeFormat,$obsTime)." (".gmdate('H:i',$obsTime).'Z)\n';
	$out .= "Status: ".$StatusLookup[$M[4]]['legend'].'\n';
	$out .= "Level:  ".$M[7]." ".$M[6].'\n\n';

	$t = !empty($M[8])?$M[8]." ".$M[6]:'n/a';
	$out .= "ACT Stage:  $t".'\n';
	$t = !empty($M[12])?$M[12]." ".$M[6]:'n/a';
	$out .= "FLD Stage:  $t".'\n';
	$t = !empty($M[13])?$M[13]." ".$M[6]:'n/a';
	$out .= "MOD Stage:  $t".'\n';
	$t = !empty($M[14])?$M[14]." ".$M[6]:'n/a';
	$out .= "MAJ Stage:  $t".'\n';
	
	
# last line of popup
	$out .= "----------------------------------------------------------";
	$out = str_replace('"',"'",$out);
  return($out);	
}

#---------------------------------------------------------------------------

// ------------ distance calculation function ---------------------
   
    //**************************************
    //     
    // Name: Calculate Distance and Radius u
    //     sing Latitude and Longitude in PHP
    // Description:This function calculates 
    //     the distance between two locations by us
    //     ing latitude and longitude from ZIP code
    //     , postal code or postcode. The result is
    //     available in miles, kilometers or nautic
    //     al miles based on great circle distance 
    //     calculation. 
    // By: ZipCodeWorld
    //
    //This code is copyrighted and has
	// limited warranties.Please see http://
    //     www.Planet-Source-Code.com/vb/scripts/Sh
    //     owCode.asp?txtCodeId=1848&lngWId=8    //for details.    //**************************************
    //     
    /*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/
    /*:: :*/
    /*:: This routine calculates the distance between two points (given the :*/
    /*:: latitude/longitude of those points). It is being used to calculate :*/
    /*:: the distance between two ZIP Codes or Postal Codes using our:*/
    /*:: ZIPCodeWorld(TM) and PostalCodeWorld(TM) products. :*/
    /*:: :*/
    /*:: Definitions::*/
    /*::South latitudes are negative, east longitudes are positive:*/
    /*:: :*/
    /*:: Passed to function::*/
    /*::lat1, lon1 = Latitude and Longitude of point 1 (in decimal degrees) :*/
    /*::lat2, lon2 = Latitude and Longitude of point 2 (in decimal degrees) :*/
    /*::unit = the unit you desire for results:*/
    /*::where: 'M' is statute miles:*/
    /*:: 'K' is kilometers (default):*/
    /*:: 'N' is nautical miles :*/
    /*:: United States ZIP Code/ Canadian Postal Code databases with latitude & :*/
    /*:: longitude are available at http://www.zipcodeworld.com :*/
    /*:: :*/
    /*:: For enquiries, please contact sales@zipcodeworld.com:*/
    /*:: :*/
    /*:: Official Web site: http://www.zipcodeworld.com :*/
    /*:: :*/
    /*:: Hexa Software Development Center � All Rights Reserved 2004:*/
    /*:: :*/
    /*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/
  function GML_distance($lat1, $lon1, $lat2, $lon2) { 
    $theta = $lon1 - $lon2; 
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta)); 
    $dist = acos($dist); 
    $dist = rad2deg($dist); 
    $miles = $dist * 60 * 1.1515;
//    $unit = strtoupper($unit);
	$bearingDeg = fmod((rad2deg(atan2(sin(deg2rad($lon2) - deg2rad($lon1)) * 
	   cos(deg2rad($lat2)), cos(deg2rad($lat1)) * sin(deg2rad($lat2)) - 
	   sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon2) - deg2rad($lon1)))) + 360), 360);

	$bearingWR = GML_direction($bearingDeg);
	
    $km = round($miles * 1.609344); 
    $kts = round($miles * 0.8684);
	$miles = round($miles);
	return(array($miles,$km,$bearingDeg,$bearingWR));
  }

#---------------------------------------------------------------------------

function GML_direction($degrees) {
   // figure out a text value for compass direction
   // Given the direction, return the text label
   // for that value.  16 point compass
   $winddir = $degrees;
   if ($winddir == "n/a") { return($winddir); }

  if (!isset($winddir)) {
    return "---";
  }
  if (!is_numeric($winddir)) {
	return($winddir);
  }
  $windlabel = array ("N","NNE", "NE", "ENE", "E", "ESE", "SE", "SSE", "S",
	 "SSW","SW", "WSW", "W", "WNW", "NW", "NNW");
  $dir = $windlabel[ (integer)fmod((($winddir + 11) / 22.5),16) ];
  return($dir);

} // end function GML_direction	
