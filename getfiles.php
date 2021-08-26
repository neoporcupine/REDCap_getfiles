<?php
/* 
This script uses the REDCap API via PHP to export all of the uploaded files for a project

### SETUP

Install PHP on your computer
* enable curl, mbstring, openssl, xmlrpc in php.ini (remove the ; from the start of extension=)
* add php to your path, example if you installed php to c:\php\
	CtrlPanel > System > Advanced System Properties > Environment Variables > System Variables > Path > Edit > New > C:\PHP\

Create a folder to contain the exported uploaded files
	Edit $ExportFolder below with this folder path

Edit $GLOBALS['api_url'] to contain your REDCap server domain with /api/ at the end

REDCap - create an export API token
	Edit $GLOBALS['api_token'] to contain your token key value

### OPERATION

Open cmd (tap windows key then type CMD and tap enter)
Change folder to the one that contains your getfiles.php
	cd c:\projects\myproj01\files\
	php getfiles.php

### NOTES

Administration fields
	<recordID>
	redcap_event_name 
	redcap_repeat_instrument
	redcap_repeat_instance

For every uploaded file we need:
	RECORDID  'record'  => '<value:recordid>',
	FIELDNAME 'field'   => '<value:nameOfFileField>',
	EVENTNAME 'event'   => '<value:eventname>',
	RPTINSTRNAME 'repeat_instrument' => '<value:nameOfRepeatedInstrument>',
	RPTINSTANCENR 'repeat_instance' => '<value:numberOfRepeatedInstance>'

Get fields of type "file" by exporting metadata
Export data - only admin fields and file fields = find existing uploaded files and their filenames
Process above to export files into selected export folder

Warning: filenames could be very long if you have long event names, form names, field names.
	Search for "$outfilename = " to locate code where filename is built.

*/

### USER EDITABLE FIELDS

$GLOBALS['api_token'] = '0123456789ABCDEF0123456789ABCDEF'; # "my project name" pid=1234
$GLOBALS['api_url'] = 'https://redcap.mydomain.net/api/'; # ensure trailing slash /
$ExportFolder = 'c:\projects\myproj01\files\\'; # ensure trailing \\

################################
################################
### FUNCTIONS

function myLog ($thisText) {
$myFileHndl = fopen($GLOBALS['logFileName'], "a");
fwrite($myFileHndl, $thisText);
fclose($myFileHndl);
}


function fnGetFile ($p_recordid, $p_redcap_event_name, $p_redcap_repeat_instrument, $p_redcap_repeat_instance, $p_fieldname, $p_outfilename) {
$fields = array(
	'token'   => $GLOBALS['api_token'],
	'content' => 'file',
	'action'  => 'export',
	'record'  => $p_recordid,
	'field'   => $p_fieldname,
	'event'   => $p_redcap_event_name,
	'repeat_instrument' => $p_redcap_repeat_instrument,
	'repeat_instance'   => $p_redcap_repeat_instance
);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $GLOBALS['api_url']);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
curl_setopt($ch, CURLOPT_VERBOSE, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_AUTOREFERER, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
$fc_xml = curl_exec($ch);
curl_close($ch);
# Write to file
$myFileHndl = fopen($p_outfilename, "w"); # w write, a append, r read
if ($myFileHndl) {
	fwrite($myFileHndl, $fc_xml);
	fclose($myFileHndl);
	}
}

################################
################################
### MAIN

$GLOBALS['logFileName']     = $ExportFolder.'getfiles.log';
echo "Log file = ".$GLOBALS['logFileName']."\n";

$adminFields = ['redcap_event_name','redcap_repeat_instrument','redcap_repeat_instance'];
$time_script_start = microtime(true); # Benchmarking

myLog ("\n################################\n".$GLOBALS['api_url']."\nExport all uploaded files for the project.\nExtracted files stored in $ExportFolder\nStarting:".date("Y-m-d H:i:s")."\n");

################################
### API: get the fieldname details

$fields = array(
	'token'   => $GLOBALS['api_token'],
	'content' => 'metadata',
	'format'  => 'json'
);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $GLOBALS['api_url']);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
curl_setopt($ch, CURLOPT_VERBOSE, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_AUTOREFERER, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
echo "Getting metadata (data dictionary) ... ";
$dict_json = curl_exec($ch);
$dict = json_decode($dict_json);
curl_close($ch);
echo "done! Count of all fields in dictionary = " . count($dict) . "\n";
myLog ("Metadata retrieved. Count of all fields in dictionary = " . count($dict) . "\n");

## List file uploade fields
echo "ID = " . $dict[0]->field_name ."\n";
$FieldList = [];
$NrFileFields = 0;
for ($i = 0; $i < count($dict); $i++) {
	if ($dict[$i]->field_type == 'file') {
		echo "Field#".$i . " = " . $dict[$i]->field_name ."\n";
		$NrFileFields += 1;
		$FieldList[] = $dict[$i]->field_name;
		}
	}
if ($NrFileFields == 0) {
	echo "There are no file upload fields in this project.\n";
	myLog ("There are no file upload fields in this project. *EXIT*\n");
	exit(0); # STOP HERE!
	}

################################
### API: get rows of data, look in File Fields to see if value, then download those values

$ExportFieldList = array_merge(array($dict[0]->field_name), $FieldList);

$fields = array(
	'token'   => $GLOBALS['api_token'],
	'content' => 'record',
	'format'  => 'json',
	'type'    => 'flat',
	'fields'  => $ExportFieldList
);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $GLOBALS['api_url']);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
curl_setopt($ch, CURLOPT_VERBOSE, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_AUTOREFERER, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
echo "Getting data ... ";
$data_json = curl_exec($ch);
$data_arr = json_decode($data_json);
curl_close($ch);
echo "done! Nr rows in data = " . count($data_arr) . "\n";
myLog ("Project data retrieved. Count of all rows in data = " . count($data_arr) . "\n");
if (count($data_arr) < 1) {
	echo "No data in the project.";
	myLog ("No data in the project. *EXIT*\n");
	exit(0); # STOP HERE!
	}

################################
### Process exported data and meta data -> call fnGetFile (API) for each file uploaded

for ($d = 0; $d < count($data_arr); $d++) { # each row of data
	for ($f = 0; $f < count($FieldList); $f++) { # each filefield
		// $v = $data_arr[$d]->myupload . " " . $FieldList[$f];
		$v = $data_arr[$d]->{$FieldList[$f]};
		if ($v != "") { # we have a value in a file field!
			$recordid = $data_arr[$d]->{$dict[0]->field_name};
			if (isset($data_arr[$d]->redcap_event_name)) $redcap_event_name = $data_arr[$d]->redcap_event_name; else $redcap_event_name = "";
			if (isset($data_arr[$d]->redcap_repeat_instrument)) $redcap_repeat_instrument = $data_arr[$d]->redcap_repeat_instrument; else $redcap_repeat_instrument = "";
			if (isset($data_arr[$d]->redcap_repeat_instance)) $redcap_repeat_instance = $data_arr[$d]->redcap_repeat_instance; else $redcap_repeat_instance = "";
			$outfilename = $ExportFolder . $recordid ."--". $FieldList[$f] ."--". $redcap_event_name . $redcap_repeat_instrument . $redcap_repeat_instance ."-". $v;
			$s = $dict[0]->field_name ." = ". $recordid;
			$s .= "; event_name = ". $redcap_event_name;
			$s .= "; rpt_instrmnt = ". $redcap_repeat_instrument;
			$s .= "; rpt_instance = ". $redcap_repeat_instance;
			$s .= "; ". $FieldList[$f] ." = ". $v."\n";
			$s .= "\t".$outfilename ."\n";
			echo $s;
			myLog($s);
			fnGetFile ($recordid, $redcap_event_name, $redcap_repeat_instrument, $redcap_repeat_instance, $FieldList[$f], $outfilename);
			}
		}
	}

# Benchmark
$time_script_end = microtime(true);
$time_script_time = ($time_script_end - $time_script_start);
if ($time_script_time < 1) {$time_script_time = "less than 1 second";}
elseif ($time_script_time < 60) {$time_script_time = round($time_script_time,2)." seconds";}
else {$time_script_time = round($time_script_time/60,2)." minutes";}

echo "Done! ".date("Y-m-d H:i:s"). ", taking ".$time_script_time." to export.\n";
myLog ("Done! ".date("Y-m-d H:i:s"). ", taking ".$time_script_time." to export.\n");

?>
