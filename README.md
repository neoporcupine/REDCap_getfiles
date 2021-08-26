# REDCap getfiles

## Brief

This script uses REDCap's API to download a project's uploaded files (via forms with type "file"), one at a time, placing the files in a designated folder.

## Overview

REDCap is a data collection package that operates via a web interface. The software is available free of charge, but is licensed; you have to be a qualifying institution. For more information on REDCap visit [Project REDCap](https://projectredcap.org/)

Projects in REDCap allow for files to be uploaded by the person entering data. This may be documents, images, audio or video files, limited to a size that is set by the server administrator. These files are downloadable by appropriately permissioned persons via the web interface

Data Exports, Reports, and Stats → Other Export Options → ZIP file of uploaded files (all records)

However, this file can be massive and may take such a long time to construct that the script hits the server's maximum execution time, failing the process with the downloader receiving an error message such as "504 gateway timeout".

This script bypasses the web interface zip process, using a seperate API call for each of the uploaded files.

## Compatibility

This script requires three different REDCap API calls:
* 'content' => 'metadata'
* 'content' => 'record'
* 'content' => 'file', 'action' => 'export'

While these API calls have been available back through a long history of REDCap versions, it is highly recommended that you keep your REDCap server up to date as much as possible to ensure you have the fewest bugs and the best security patches applied.

The code was developed using PHP 7.4.21 and not tested on older versions of PHP.

## Setup

### Install PHP

Skip this if you already have PHP installed. If installed properly then you should be able to run cmd, and type the following command to reveal which version of PHP is currently installed.

```cmd
php -v
```

You need to have PHP on your local computer where you are running the script. There are plenty of guides available to install PHP. Note that you do NOT have to setup IIS for this script to work.

[PHP for Windows](https://windows.php.net/)

Enable curl, mbstring, openssl, xmlrpc in php.ini, by removing the semicolon (";") from the start of line "extension="

Add php to your path, example if you installed php to c:\php\

CtrlPanel → System → Advanced System Properties → Environment Variables → System Variables → Path → Edit → New → C:\PHP\

### Create a folder to contain the exported uploaded files

Note that PHP will need permissions to access this folder when running, this is nearly never an issues excepting some network drive configurations, and/or running the script as a scheduled process.

Edit the gefiles.php script, find $ExportFolder and ensure this is pointing at the folder you just created.

Generally I recommend placing a copy of getfiles.php in this folder and running the script from here. If you need to place the getfiles.php file elsewhere then make note of this folder.

```php
$ExportFolder = 'p:\projects\myproj01\files\\'; # ensure trailing \\
```

### Generate an API token in REDCap

Use the REDCap interface to request an EXPORT API token for your project. This usually requires administrator approval and will be sent to you via email.

Edit the gefiles.php script, find $GLOBALS['api_token'] and the associated string value contains your REDCap token

```php
$GLOBALS['api_token'] = '0123456789ABCDEF0123456789ABCDEF'; # "my project name" pid=1234
```

### Update the API url for your REDCap server

Edit the gefiles.php script, find $GLOBALS['api_url'] and adjust to match your REDCap server domain

```php
$GLOBALS['api_url'] = 'https://redcap.mydomain.net/api/'; # ensure trailing slash /
```

## Operation

Once properly setup, you will need to open a command prompt / shell.

On Windows this is done a number of ways, example: tap the windows key then type *CMD* and tap enter.

Change your folder to the one that contains your getfiles.php, then invoke php with getfiles.php as the parameter, example:

```dos
p:
cd p:\projects\myproj01\files\
php getfiles.php
```

## NOTE

[1] Warning: filenames could be very long if you have long event names, form names, field names. Search for "$outfilename = " to locate code where filename is built.

[2] REDCap API documentation for file export does not state that there is an optional field for repeat_instrument which is essential if there is more than one repeated instrument within the project-event. Also, blank values ("") can be assigned without issue for data retrieval.

```php
$fields = array(
	'token'   => $GLOBALS['api_token'],
	'content' => 'file',
	'action'  => 'export',
	'record'  => $recordid,
	'field'   => $fieldname,
	'event'   => $redcap_event_name,
	'repeat_instrument' => $redcap_repeat_instrument,
	'repeat_instance'   => $redcap_repeat_instance
);
```

## Contributing

I use this for my own projects and as a utility for projects that contact me when they run into the "gateway timeout" issue.

Please, if you have any ideas, just [open an issue][issues] and tell me what you think.

If you'd like to contribute, please fork the repository and make changes as you need.

## Licensing

The script is provided as is. This project is licensed under Unlicense license. This license does not require you to take the license with you to your project.

