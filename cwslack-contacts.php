<?php
/* 	
	CWSlack-SlashCommands
    Copyright (C) 2016  jundis

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>. 
*/


ini_set('display_errors', 1); //Display errors in case something occurs
header('Content-Type: application/json'); //Set the header to return JSON, required by Slack
require_once 'config.php';
require_once 'functions.php';

if(empty($_GET['token']) || ($_GET['token'] != $slackcontactstoken)) die("Slack token invalid."); //If Slack token is not correct, kill the connection. This allows only Slack to access the page for security purposes.
if(empty($_GET['text'])) die("No text provided."); //If there is no text added, kill the connection.

$apicompanyname = strtolower($companyname); //Company name all lower case for api auth. 
$authorization = base64_encode($apicompanyname . "+" . $apipublickey . ":" . $apiprivatekey); //Encode the API, needed for authorization.
$exploded = explode(" ",$_GET['text']); //Explode the string attached to the slash command for use in variables.

//Check to see if the first command in the text array is actually help, if so redirect to help webpage detailing slash command use.
if ($exploded[0]=="help") {
	$test=json_encode(array("parse" => "full", "response_type" => "in_channel","text" => "Please visit " . $helpurl . " for more help information","mrkdwn"=>true)); //Encode a JSON response with a help URL.
	echo $test; //Return the JSON
	return; //Kill the connection.
}

$firstname=NULL; //Create a first name variable and set it to Null
$lastname=NULL; //Create a last name variable and set it to Null
$url=NULL; //Create a URL variable and set it to Null.

if (array_key_exists(0,$exploded)) //If the first part of the array exists (always will)
{
	$lastname = $exploded[0];
	$url = $connectwise . "/v4_6_release/apis/3.0/company/contacts?conditions=lastName%20like%20%27" . $lastname . "%27"; //Set contact API url
}
if (array_key_exists(1,$exploded)) //If two parts of the array exists
{
	$lastname = $exploded[1]; //Set the second portion to last name
	$firstname = $exploded[0]; //Set the first portion to first name
	
	$url = $connectwise . "/v4_6_release/apis/3.0/company/contacts?conditions=lastName%20like%20%27" . $lastname . "%27%20and%20firstName%20like%20%27" . $firstname . "%27"; //Set contact API url to include first and last name.
}

$utc = time(); //Get the time.
// Authorization array. Auto encodes API key for auhtorization above.
$header_data =array(
 "Authorization: Basic ". $authorization,
);

//Need to create array before hand to ensure no errors occur.
$dataTData = array();

//-
//cURL connection to ConnectWise to pull Company API.
//-
$dataTData = cURL($url, $header_data);

if($dataTData==NULL) //If no contact is returned or your API URL is incorrect.
{
	die("No contact found or your API URL is incorrect."); //Kill the connection.
}

$return="Nothing!"; //Create return value and set to a basic message just in case.
$company=$dataTData[0]->company; //Set company array for easier reference later on.
$compurl=$company->_info;

//Company phone #
$dataCData = cURL($compurl->company_href, $header_data); //Decode the JSON returned by the CW API.
$cphone = NULL; // Just in case.

if ($dataCData->phoneNumber != NULL && $dataCData->phoneNumber != NULL)
{
    $cphone = preg_replace('~.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*~', '($1) $2-$3', $dataCData->phoneNumber);
}


$sphone = NULL; // Just in case.

if($dataTData[0]->site!=NULL) {
    $site = $dataTData[0]->site;

    $siteurl = $site->_info;
    //Company phone #
    $dataSData = cURL($siteurl->site_href, $header_data); //Decode the JSON returned by the CW API.

    if ($dataSData->phoneNumber != NULL && $dataSData->phoneNumber != NULL)
    {
        $sphone = preg_replace('~.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*~', '($1) $2-$3', $dataSData->phoneNumber);
    }
}

$text="No contact info found."; //Set catch error "just in case"

if(array_key_exists("communicationItems",$dataTData[0]) && $dataTData[0]->communicationItems != NULL)
{
    $comms=$dataTData[0]->communicationItems; //Set communications array for iteration.
    $text=""; //Set blank text varaible "just in case"
    if($cphone != NULL || $sphone != NULL) //Check if one is not null and has data
    {
        $text = ""; //Set text to blank for proper processing
    }
    if($cphone != NULL) //Check if cphone is null
    {
        $text=$text . "Company Phone: " . $cphone . "\n"; //If not, set text and add new line
    }
    if($sphone != NULL && $sphone != $cphone) //Check if sphone is null AND if sphone is the same as cphone, skip if both are true.
    {
        $text=$text . "Site Phone: " . $sphone . "\n";
    }
    //Iteration block to search through all contact types on the user.
    foreach($comms as $item) {
        $type = $item->type; //Set the type variable to whatever the contact type is, this would be Email or Direct or whatever you have it set to in CW.
        $formatted = preg_replace('~.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*~', '($1) $2-$3', $item->value); //Format phone numbers
        $text = $text . $type->name . ": " . $formatted . "\n"; //Create a new line for each iteration,
    }
}
else
{
    if($cphone != NULL || $sphone != NULL) //Check if one is not null and has data
    {
        $text = ""; //Set text to blank for proper processing
    }
    if($cphone != NULL) //Check if cphone is null
    {
        $text=$text . "Company Phone: " . $cphone . "\n"; //If not, set text and add new line
    }
    if($sphone != NULL && $sphone != $cphone) //Check if sphone is null AND if sphone is the same as cphone, skip if both are true.
    {
        $text=$text . "Site Phone: " . $sphone . "\n";
    }
}


$return =array(
	"parse" => "full", //Parse all text.
	"response_type" => "in_channel", //Send the response in the channel
	"attachments"=>array(array(
		"fallback" => "Contact Info for " . $dataTData[0]->firstName . " " . $dataTData[0]->lastName, //Fallback for notifications
		"title" => "Company: " . $company->name, //Set bolded title text
		"pretext" => "Contact Info for " . $dataTData[0]->firstName . " " . $dataTData[0]->lastName, //Set pretext
		"text" => $text, //Set text to be returned
		"mrkdwn_in" => array( //Set markdown values
			"text",
			"pretext"
			)
		))
	);


echo json_encode($return, JSON_PRETTY_PRINT); //Return properly encoded arrays in JSON for Slack parsing.

?>