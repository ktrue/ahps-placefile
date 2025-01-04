<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
#----------------------------------------------------------------------------
/*
  Script: get-ahps-levels.php

	Purpose: retrieve current river/stream levels from NOAA AHPS mapserver in JSON format
		
	Inputs; URL https://mapservices.weather.noaa.gov/eventdriven/rest/services/water/ahps_riv_gauges/MapServer/0/query?&geometry=%7Bxmin%3A+-128%2C+ymin%3A+23%2C+xmax%3A+-65%2C+ymax%3A+36%7D&geometryType=esriGeometryEnvelope&spatialRel=esriSpatialRelIntersects&outFields=*&returnGeometry=true&returnIdsOnly=false&returnCountOnly=false&returnZ=false&returnM=false&returnDistinctValues=false&f=pjson
					
	Output: ahps-levels-inc.php file with $AHPS array with one entry per gauge reporting
	 
  Script by Ken True - webmaster@saratoga-weather.org

*/
#----------------------------------------------------------------------------
// Version 1.00 - 24-Jul-2023 - Initial Release
// Version 1.01 - 29-Mar-2024 - update for new NWC mapservice URL
// Version 1.02 - 23-May-2024 - added checks for complete downloads from mapserver
// Version 1.03 - 28-May-2024 - added retries when connect timeout happens
// Version 1.04 - 04-Jan-2025 - added debugging features for fetch issues
// -------------Settings ---------------------------------
  $cacheFileDir = './';      // default cache file directory
  $ourTZ = 'America/Los_Angeles';
	
// -------------End Settings -----------------------------
//

$GMLversion = 'get-ahps-levels.php V1.04 - 04-Jan-2025';
//$NOAA_URL = 'https://mapservices.weather.noaa.gov/eventdriven/rest/services/water/ahps_riv_gauges/MapServer/0/query?&geometryType=esriGeometryEnvelope&spatialRel=esriSpatialRelIntersects&outFields=*&returnGeometry=true&returnIdsOnly=false&returnCountOnly=false&returnZ=false&returnM=false&returnDistinctValues=false&f=pjson'; // new location 15-June-2016

// March 27, 2024 new URL
// https://mapservices.weather.noaa.gov/eventdriven/rest/services/water/riv_gauges/MapServer
$NOAA_URL = 'https://mapservices.weather.noaa.gov/eventdriven/rest/services/water/riv_gauges/MapServer/0/query?&geometryType=esriGeometryEnvelope&spatialRel=esriSpatialRelIntersects&outFields=*&returnGeometry=true&returnIdsOnly=false&returnCountOnly=false&returnZ=false&returnM=false&returnDistinctValues=false&f=json'; // new location 15-June-2016


$geos = array( # do overlapping queries to get around 10000 returns limit on mapserver
'CONUS-East' => '&geometry=%7Bxmin%3A+-96.67%2C+ymin%3A+19.20%2C+xmax%3A+-66.89%2C+ymax%3A+49.39%7D',
'CONUS-West' => '&geometry=%7Bxmin%3A+-159.67%2C+ymin%3A+19.20%2C+xmax%3A+-94.57%2C+ymax%3A+49.39%7D',
);
//
$NOAAcacheName = $cacheFileDir."ahps-json.txt";
$outputFile    = 'ahps-levels-inc.php';
// Set maximum number of seconds (can have floating-point) to wait for feed before displaying page without feed
$numberOfSeconds=15;   
$retries = 2; // number of retries to get data.
// ---------- end of settings -----------------------

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

// --------- search for nearby metars ------------
  if (!function_exists('date_default_timezone_set')) {
	putenv("TZ=" . $ourTZ);
#	$Status .= "<!-- using putenv(\"TZ=$ourTZ\") -->\n";
    } else {
	date_default_timezone_set("$ourTZ");
#	$Status .= "<!-- using date_default_timezone_set(\"$ourTZ\") -->\n";
}
header('Content-type: text/plain,charset=ISO-8859-1');

global $Debug;

$Debug = "<!-- $GMLversion -->\n";
$Debug .= "<!-- run on ".date('D, d-M-Y H:i:s T'). " -->\n";
$Debug .= "<!--        ".gmdate('D, d-M-Y H:i:s T'). " -->\n";

$output = '';

$JSON = array('features' => array());
#----------------------------------------------------------------------------
# get JSON data from URLs consolidated into $JSON['features'] array
#----------------------------------------------------------------------------

foreach ($geos as $geoname => $geoquery) {
	$gotit = false;
	for ($i=0;$i<$retries;$i++) {
	  $rawHTML = GML_fetchUrlWithoutHanging($NOAA_URL.$geoquery);
	
	  $Debug .= "<!-- AHPS returned ".strlen($rawHTML)." bytes -->\n";
		if(strlen($rawHTML) > 100000) { break; }
		$thisTry = $i+1;
		$Debug .= "<!-- retrying. Try $thisTry of $retries failed to get contents. -->\n";
		sleep(15);
	}
	
	file_put_contents(str_replace('.txt',$geoname.'.txt',$NOAAcacheName),$rawHTML);
	if(strlen($rawHTML) < 5000) {
		$Debug .= "<!-- $geoname query returns only ".strlen($rawHTML)." bytes. -- skipping.\n";
		continue;
	}
	$tJSON = json_decode($rawHTML,true);
	if (strlen($rawHTML > 500) and function_exists('json_last_error')) { // report status, php >= 5.3.0 only
		switch (json_last_error()) {
		case JSON_ERROR_NONE:
			$JSONerror = '- No errors';
			break;
	
		case JSON_ERROR_DEPTH:
			$JSONerror = '- Maximum stack depth exceeded';
			break;
	
		case JSON_ERROR_STATE_MISMATCH:
			$JSONerror = '- Underflow or the modes mismatch';
			break;
	
		case JSON_ERROR_CTRL_CHAR:
			$JSONerror = '- Unexpected control character found';
			break;
	
		case JSON_ERROR_SYNTAX:
			$JSONerror = '- Syntax error, malformed JSON';
			break;
	
		case JSON_ERROR_UTF8:
			$JSONerror = '- Malformed UTF-8 characters, possibly incorrectly encoded';
			break;
	
		default:
			$JSONerror = '- Unknown error';
			break;
		}
	
		$Debug.= "<!-- JSON decode $JSONerror -->\n";
		if (json_last_error() !== JSON_ERROR_NONE) {
			#$Debug.= "<!-- content='" . print_r($rawHTML, true) . "' -->\n";
		}
	}
	$tQuery = urldecode($geoquery);
	$Debug .= "<!-- $geoname query returns ".count($tJSON['features']). " entries using '$tQuery' bounds -->\n\n";
  $JSON['features'] = array_merge($JSON['features'],$tJSON['features']);
}

$rivers = isset($JSON['features'])?$JSON['features']:array();

$Debug .= "<!-- .. ".count($rivers)." total entries returned for processing -->\n";

if(count($rivers) < 8000 ){
	$Debug .= "<!-- Oops.. insufficient data returned from $NOAA_URL\n - aborting. -->\n";
	$Debug = preg_replace('|<!--|is','',$Debug);
	$Debug = preg_replace('|-->|is','',$Debug);
	# print "<pre>\n";
	print $Debug;
	# print "</pre>\n";
	exit;
}
#----------------------------------------------------------------------------
#  now process $JSON['features'] array one entry at a time
#----------------------------------------------------------------------------

$statesFound = array();
$condsFound  = array();
$rData = array();
/*

  {
   "attributes": {
    "objectid": 1,
    "gaugelid": "AAIT2",
    "status": "no_flooding",
    "location": "Manchaca Road at Austin",
    "latitude": 30.221111,
    "longitude": -97.793333,
    "waterbody": "Williamson Creek",
    "state": "TX",
    "obstime": "2023-07-22 21:30:00",
    "units": "ft",
    "lowthreshu": "ft",
    "wfo": "ewx",
    "hdatum": "NAD83/WGS84",
    "pedts": "HGIRG",
    "secunit": "kcfs",
    "url": "https://water.weather.gov/ahps2/hydrograph.php?wfo=ewx&gage=aait2",
    "idp_source": "national_shapefile_obs",
    "idp_subset": "default",
    "observed": "1.99",
    "action": " ",
    "forecast": null,
    "lowthresh": "0.00",
    "secvalue": "-999.00",
    "flood": "13.00",
    "moderate": "14.00",
    "major": "18.00",
    "idp_filedate": 1690063451000,
    "idp_ingestdate": 1690063556000,
    "idp_current_forecast": null,
    "idp_time_series": null,
    "idp_issueddate": null,
    "idp_validtime": null,
    "idp_validendtime": null,
    "idp_fcst_hour": null
   },
   "geometry": {
    "x": -97.793332999718132,
    "y": 30.221110999573114
   }
  },
*/
$StatesSeen = array();
$StatusSeen = array();
$GaugeSeen  = array();

foreach ($rivers as $i => $R) {
	$RA = $R['attributes'];
	$Data = array();
	
	$Data[] = $RA['latitude'];
	$Data[] = $RA['longitude'];
	$Data[] = $RA['waterbody'] . ' at '. $RA['location'];

	$nState = $RA['state'];
	$Data[] = $nState;
	if(isset($StatesSeen[$nState])) {
		$StatesSeen[$nState]++;
	} else {
		$StatesSeen[$nState] = 1;
	}

	#$nStatus = ucwords(str_replace('_',' ',$RA['status']));
	$nStatus = $RA['status'];
	$Data[] = $nStatus;
	if(isset($StatusSeen[$nStatus])) {
		$StatusSeen[$nStatus]++;
	} else {
		$StatusSeen[$nStatus]= 1;
	}
	
	$Data[] = $RA['obstime'];
	$Data[] = $RA['units'];
	$Data[] = $RA['observed'];
	$Data[] = trim($RA['action']);
	$Data[] = $RA['forecast'];
	$Data[] = trim($RA['lowthresh']);
	$Data[] = trim($RA['secvalue']);
	$Data[] = trim($RA['flood']);
	$Data[] = trim($RA['moderate']);
	$Data[] = trim($RA['major']);
	
	$nData = join('|',$Data);
	if(isset($rData[$RA['gaugelid']])) {
		$GaugeSeen[$RA['gaugelid']] = "Old: ".$rData[$RA['gaugelid']]."\nNew: ".$nData;
	}
	$rData[$RA['gaugelid']] = $nData;
	
}

#----------------------------------------------------------------------------
# $rData now has processed gauge data -- emit reports and save output file
#----------------------------------------------------------------------------


ksort($rData);
ksort($StatesSeen);
ksort($GaugeSeen);

file_put_contents($outputFile,"<?php \n\$AHPS = ".var_export($rData,true).";\n");
$Debug .= "\n<!-- ..file $outputFile saved. -->\n";
$t = array();
foreach($StatesSeen as $state => $count) {
	$t[] = $state . "=" . $count;
}
$Debug .= "<!-- .. ".count($StatesSeen)." states found: \n".wordwrap(join(', ',$t),72). " -->\n";
$Debug .= "<!-- .. ".count($StatusSeen)." status types found\n".var_export($StatusSeen,true)."\n -->\n";
$Debug .= "<!-- .. ".count($GaugeSeen). " duplicated gauges from querys were consolidated -->\n";
#$Debug .= "<!-- ".count($GaugeSeen). " duplicated gauges from query consolidated\n".var_export($GaugeSeen,true)."\n -->\n";

$Debug .= "\n<!-- ..finished processing -->\n";

$Debug = preg_replace('|<!--|is','',$Debug);
$Debug = preg_replace('|-->|is','',$Debug);
#print "<pre>\n";
print $Debug;
#print "</pre>\n";

#----------------------------------------------------------------------------

// ----------------------------functions ----------------------------------- 

// get contents from one URL and return as string 
 function GML_fetchUrlWithoutHanging($url,$useFopen=false) {
// get contents from one URL and return as string 
  global $Debug, $needCookie,$numberOfSeconds;
  
  $overall_start = time();
  if (! $useFopen) {

// Thanks to Curly from ricksturf.com for the cURL fetch functions

  $data = '';
  $domain = parse_url($url,PHP_URL_HOST);
  $theURL = str_replace('nocache','?'.$overall_start,$url);        // add cache-buster to URL if needed
  $Debug .= "<!-- ".date('r')." -->\n";
  $Debug .= "<!-- curl fetching '$theURL' -->\n";
  $ch = curl_init();                                           // initialize a cURL session
  curl_setopt($ch, CURLOPT_URL, $theURL);                         // connect to provided URL
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                 // don't verify peer certificate
  curl_setopt($ch, CURLOPT_USERAGENT, 
    'Mozilla/5.0 (get-ahps-levels.php - saratoga-weather.org)');

  curl_setopt($ch,CURLOPT_HTTPHEADER,                          // request LD-JSON format
     array (
         "Accept: text/html,text/plain,application/json"
     ));

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $numberOfSeconds);  //  connection timeout
  curl_setopt($ch, CURLOPT_TIMEOUT, $numberOfSeconds);         //  data timeout
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);              // return the data transfer
  curl_setopt($ch, CURLOPT_NOBODY, false);                     // set nobody
  curl_setopt($ch, CURLOPT_HEADER, false);                      // include header information
//  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);              // follow Location: redirect
//  curl_setopt($ch, CURLOPT_MAXREDIRS, 1);                      //   but only one time
  if (isset($needCookie[$domain])) {
    curl_setopt($ch, $needCookie[$domain]);                    // set the cookie for this request
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);             // and ignore prior cookies
    $Debug .=  "<!-- cookie used '" . $needCookie[$domain] . "' for GET to $domain -->\n";
  }

  $data = curl_exec($ch);                                      // execute session

  if(curl_error($ch) <> '') {                                  // IF there is an error
   $Debug .= "<!-- curl Error: ". curl_error($ch) ." -->\n";        //  display error notice
  }
  $cinfo = curl_getinfo($ch);                                  // get info on curl exec.
/*
curl info sample
Array
(
[url] => http://saratoga-weather.net/clientraw.txt
[content_type] => text/plain
[http_code] => 200
[header_size] => 266
[request_size] => 141
[filetime] => -1
[ssl_verify_result] => 0
[redirect_count] => 0
  [total_time] => 0.125
  [namelookup_time] => 0.016
  [connect_time] => 0.063
[pretransfer_time] => 0.063
[size_upload] => 0
[size_download] => 758
[speed_download] => 6064
[speed_upload] => 0
[download_content_length] => 758
[upload_content_length] => -1
  [starttransfer_time] => 0.125
[redirect_time] => 0
[redirect_url] =>
[primary_ip] => 74.208.149.102
[certinfo] => Array
(
)

[primary_port] => 80
[local_ip] => 192.168.1.104
[local_port] => 54156
)
*/
  $Debug .= "<!-- HTTP stats: " .
    " RC=".$cinfo['http_code'];
	if(isset($cinfo['primary_ip'])) {
		$Debug .= " dest=".$cinfo['primary_ip'] ;
	}
	if(isset($cinfo['primary_port'])) { 
	  $Debug .= " port=".$cinfo['primary_port'] ;
	}
	if(isset($cinfo['local_ip'])) {
	  $Debug .= " (from sce=" . $cinfo['local_ip'] . ")";
	}
	$Debug .= 
	"\n      Times:" .
    " dns=".sprintf("%01.3f",round($cinfo['namelookup_time'],3)).
    " conn=".sprintf("%01.3f",round($cinfo['connect_time'],3)).
    " pxfer=".sprintf("%01.3f",round($cinfo['pretransfer_time'],3));
	if($cinfo['total_time'] - $cinfo['pretransfer_time'] > 0.0000) {
	  $Debug .=
	  " get=". sprintf("%01.3f",round($cinfo['total_time'] - $cinfo['pretransfer_time'],3));
	}
    $Debug .= " total=".sprintf("%01.3f",round($cinfo['total_time'],3)) .
    " secs -->\n";

  //$Debug .= "<!-- curl info\n".print_r($cinfo,true)." -->\n";
  curl_close($ch);                                              // close the cURL session
  //$Debug .= "<!-- raw data\n".$data."\n -->\n"; 
#  $stuff = explode("\r\n\r\n",$data); // maybe we have more than one header due to redirects.
#  $content = (string)array_pop($stuff); // last one is the content
#  $headers = (string)array_pop($stuff); // next-to-last-one is the headers
  if($cinfo['http_code'] <> '200') {
    $Debug .= "<!-- oops.. RC=".$cinfo['http_code']. " -->\n";
    if(isset($headers)) {
      $Debug .= "<!-- headers returned:\n".$headers."\n -->\n"; 
      } else {
      $Debug .= "<!-- no headers returned -->\n";
    }
    $Debug .= "<!-- Data returned \n$data\n -->\n";
  }
  return $data;                                                 // return headers+contents

 } else {
//   print "<!-- using file_get_contents function -->\n";
   $STRopts = array(
	  'http'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (get-ahps-levels.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  ),
	  'https'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (get-ahps-levels.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  )
	);
	
   $STRcontext = stream_context_create($STRopts);

   $T_start = GML_fetch_microtime();
   $xml = file_get_contents($url,false,$STRcontext);
   $T_close = GML_fetch_microtime();
   $headerarray = get_headers($url,0);
   $theaders = join("\r\n",$headerarray);
   $xml = $theaders . "\r\n\r\n" . $xml;

   $ms_total = sprintf("%01.3f",round($T_close - $T_start,3)); 
   $Debug .= "<!-- file_get_contents() stats: total=$ms_total secs -->\n";
   $Debug .= "<!-- get_headers returns\n".$theaders."\n -->\n";
//   print " file() stats: total=$ms_total secs.\n";
   $overall_end = time();
   $overall_elapsed =   $overall_end - $overall_start;
   $Debug .= "<!-- fetch function elapsed= $overall_elapsed secs. -->\n"; 
//   print "fetch function elapsed= $overall_elapsed secs.\n"; 
   return($xml);
 }

}    // end GML_fetchUrlWithoutHanging

// ------------------------------------------------------------------

function GML_fetch_microtime()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}
   
#----------------------------------------------------------------------------
