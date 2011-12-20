<?php

/*

Copyright 2011 LinkedIn Corporation

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.

*/

// if you decide to change the name of the constants here
// or the variable name of the oauth object, then you need 
// to fix the code throughout that references it

define("API_CONSUMER_KEY", "xxxxxxxxxxxxxx");
define("API_CONSUMER_SECRET", "xxxxxxxxxxxxxx");

include("helpers.php");

$oauth = new OAuth(API_CONSUMER_KEY, API_CONSUMER_SECRET);

// We do this so request/response headers are preserved.
// This is needed to diagnose issues when requests fail.
$oauth->enableDebug();

if( file_exists(".service.dat") ) {
	$config = json_decode(file_get_contents(".service.dat"));
	if( isset($config->oauth_token) && isset($config->oauth_token_secret) ) {
		$oauth->setToken($config->oauth_token, $config->oauth_token_secret);
	} else {
		print_line("We had a service.dat file, but it didn't contain a token/secret?", null, STDERR, __LINE__);
	}
} else {
	print("We don't have a service.dat file, so we need to get access tokens!");
	
	$request_token_info = $oauth->getRequestToken("https://api.linkedin.com/uas/oauth/requestToken");
	if( $request_token_info === FALSE || empty($request_token_info) ) {
		print_line("Failed to fetch request token, debug info: %s", print_r($oauth->debugInfo, true), STDERR, __LINE__);
	}
	
	$oauth->setToken($request_token_info["oauth_token"], $request_token_info["oauth_token_secret"]);
	
	print_line("Please visit this URL:
		\nhttps://www.linkedin.com/uas/oauth/authenticate?oauth_token=%s
		\nIn your browser and then input the numerical code you are provided here: ", $request_token_info["oauth_token"]);
			
	$pin = trim(fgets(STDIN));
	
	$access_token_info = $oauth->getAccessToken("https://api.linkedin.com/uas/oauth/accessToken", "", $pin);
	if( $access_token_info === FALSE || empty($access_token_info) ) {
		print("Failed to fetch access token, debug info:");
		die(print_r($oauth->debugInfo, true));
	}
	
	$oauth->setToken($access_token_info["oauth_token"], $access_token_info["oauth_token_secret"]);
	
	file_put_contents(".service.dat", json_encode($access_token_info));
}



/*******************************
 *
 *  Reading data from LinkedIn
 *
 *******************************/



print_line("\n********A basic user profile call********");
$api_url = "http://api.linkedin.com/v1/people/~";
$oauth->fetch($api_url, null, OAUTH_HTTP_METHOD_GET);
$response = $oauth->getLastResponse(); // just a sample of how you would get the response
print_response($oauth);


print_line("\n********Get the profile in JSON********");
$api_url = "http://api.linkedin.com/v1/people/~";
$oauth->fetch($api_url, null, OAUTH_HTTP_METHOD_GET, array('x-li-format' => 'json'));
print_response($oauth);


// Now, rather than use the http header to request JSON, use the query param instead
// Note, the above format is the preferred method
print_line("\n********Get the profile in JSON using query parameter********");
$api_url = "http://api.linkedin.com/v1/people/~";
$oauth->fetch($api_url, array("format" => "json"), OAUTH_HTTP_METHOD_GET);
print_response($oauth);


print_line("\n********A basic user profile call********");
$api_url = "http://api.linkedin.com/v1/people/~/connections";
$oauth->fetch($api_url, null, OAUTH_HTTP_METHOD_GET);
print_response($oauth);


// This call extends on the above by limiting the number of results to 10
print_line("\n********Get only 10 connections - using parameters********");
$api_url = "http://api.linkedin.com/v1/people/~/connections";
$oauth->fetch($api_url, array("count" => 10), OAUTH_HTTP_METHOD_GET);
print_response($oauth);


print_line("\n********GET network updates that are CONN and SHAR********");
$api_url = "http://api.linkedin.com/v1/people/~/network/updates";
$oauth->fetch($api_url, array("type" => "SHAR", "type" => "CONN"), OAUTH_HTTP_METHOD_GET);
print_response($oauth);


print_line("\n********People Search using facets and Encoding input parameters i.e. UTF8********");
$api_url = "http://api.linkedin.com/v1/people-search:(people:(first-name,last-name,headline),facets:(code,buckets))";
$oauth->fetch($api_url, array(
							"title" => "Développeur", 
							"facet" => "industry,4", 
							"facets" => "location,industry"
						), OAUTH_HTTP_METHOD_GET);
print_response($oauth);



/*****************************
 *
 *  Writing back to LinkedIn
 *
 *****************************/



// fields used in all three share examples below
// these would usually be provided by you / the user
$comment = "Testing out the LinkedIn REST Share API";
$title = "Survey: Social networks top hiring tool - San Francisco Business Times";
$url = "http://sanfrancisco.bizjournals.com/sanfrancisco/stories/2010/06/28/daily34.html";
$image = "http://images.bizjournals.com/travel/cityscapes/thumbs/sm_sanfrancisco.jpg";
$visibility = "anyone";


// here we create some XML which is used in the next two API calls
$xmlwriter = new XMLWriter2();
$xmlwriter->openMemory();
$xmlwriter->startDocument('1.0', 'UTF-8')->startElement("share");
	$xmlwriter->addElement("comment", $comment);
	$xmlwriter->startElement("content");
		$xmlwriter->addElement("title", $title)->addElement("submitted-url", $url)->addElement("submitted-image-url", $image);
	$xmlwriter->endElement();
	$xmlwriter->startElement("visibility")->addElement("code", $visibility)->endElement();
$xmlwriter->endElement()->endDocument();
$body_xml = $xmlwriter->outputMemory(TRUE);


print_line("\n********Write to the share - using XML********");
$api_url = "http://api.linkedin.com/v1/people/~/shares";
$oauth->fetch($api_url, $body_xml, OAUTH_HTTP_METHOD_POST, array("Content-Type" => "text/xml"));
print_response($oauth);


print_line("\n********Write to the share and to twitter (if linked) - using XML********");
$api_url = "http://api.linkedin.com/v1/people/~/shares?twitter-post=true";
$oauth->fetch($api_url, $body_xml, OAUTH_HTTP_METHOD_POST, array("Content-Type" => "text/xml")); // uses same XML as above
print_response($oauth);


// here we build the JSON which is used to submit a share via JSON rather than XML
$body = new stdClass();
$body->comment = $comment;
$body->content = new stdClass();
$body->content->title = $title;
$body->content->{'submitted-url'} = $url;
$body->content->{'submitted-image-url'} = $image;
$body->visibility = new stdClass();
$body->visibility->code = $visibility;
$body_json = json_encode($body);

print_line("\n********Write to the share - using JSON********");
$api_url = "http://api.linkedin.com/v1/people/~/shares";
$oauth->fetch($api_url, $body_json, OAUTH_HTTP_METHOD_POST, array(
																"Content-Type" => "application/json",
																"x-li-format" => "json"
															  ));
print_response($oauth);

// Note that the same query param used to post to twitter for XML also applies to JSON
// To post to Twitter using JSON, just use the same $api_url as the REST example uses




/*******************************
 *
 *  Leveraging field selectors
 *
 *******************************/



print_line("\n********A basic user profile call with field selectors********");
$api_url = "http://api.linkedin.com/v1/people/~:(first-name,last-name,positions)";
$oauth->fetch($api_url, null, OAUTH_HTTP_METHOD_GET);
print_response($oauth);


print_line("\n********A basic user profile call with field selectors going into a subresource********");
$api_url = "http://api.linkedin.com/v1/people/~:(first-name,last-name,positions:(company:(name)))";
$oauth->fetch($api_url, null, OAUTH_HTTP_METHOD_GET);
print_response($oauth);


print_line("\n********A basic user profile call into a subresource return data in JSON********");
$api_url = "https://api.linkedin.com/v1/people/~/connections:(first-name,last-name,headline)?format=json";
$oauth->fetch($api_url, null, OAUTH_HTTP_METHOD_GET);
print_response($oauth);


print_line("\n********A more complicated example using postings into groups********");
$api_url = "http://api.linkedin.com/v1/groups/3297124/posts:(id,category,creator:(id,first-name,last-name),title,summary,creation-timestamp,site-group-post-url,comments,likes)";
$oauth->fetch($api_url, null, OAUTH_HTTP_METHOD_GET);
print_response($oauth);



/********************************************************************************
 *
 *  Understanding the response, creating logging, request and response headers
 *
 ********************************************************************************/




print_line("\n********A basic user profile call and response dissected********");
$api_url = "https://api.linkedin.com/v1/people/~";
$oauth->fetch($api_url, null, OAUTH_HTTP_METHOD_GET);

// show the headers
print_line("\n** Request Headers:\n%s", $oauth->debugInfo["headers_sent"]);
print_line("\n** Response Headers:\n%s\n", $oauth->debugInfo["headers_recv"]);

// show the URL requested
print_line("\n** Requested URL:\n\thttp://api.linkedin.com%s", substr(array_shift(explode("\n", $oauth->debugInfo["headers_sent"])), strpos($oauth->debugInfo["headers_sent"], " ")+1, -9));

// for the response date and format, we need to parse the headers received
$headers = http_parse_headers($oauth->debugInfo["headers_recv"]);

// Date of response
print_line("\n** Response Date:\n\t%s", $headers["Date"]);

// The LinkedIn format of the response
print_line("\n** Response LinkedIn Format:\n\t%s", $headers["X-Li-Format"]);

// these next two can get their data from the response info directly
$info = $oauth->getLastResponseInfo();

// Content-type of the response
print_line("\n** Response Content-Type:\n\t%s", $info["content_type"]);

// Demonstrate handling different http code responses
$http_code = $info["http_code"];

switch(true) {
	case $http_code >= 200 && $http_code < 300:
		print_line("\n** Status:\n\tHOORAY IT WORKED!!");
		break;
	case $http_code >= 500 && $http_code < 600:
		print_line("\n** Status:\n\tRuh Roh! An application error occured! HTTP 5XX response received.");
		break;
	case $http_code == 403:
		print_line("\n** Status:\n\tA 403 response was received. Usually this means you have reached a throttle limit.");
		break;
	case $http_code == 401:
		print_line("\n** Status:\n\tA 401 response was received. Usually this means the OAuth signature was bad.");
		break;
	case $http_code == 405:
		print_line("\n** Status:\n\tA 405 response was received. Usually this means you used the wrong HTTP method (GET when you should POST, etc).");
		break;
	default:
		print_line("\n** Status:\n\tWe got a response that doesn't commonly occur. You should report this in the forums!");
		break;
}

// Now demonstrate how to make a logging function which provides us the info we need to
// properly help debug issues. Please use the logged block from here when requesting
// help in the forums.

function log_diagnostics_info($oauth, $exception = null) {
	// we build up a string, then log it, as multiple calls to error_log() are not guaranteed to be contiguous
	$log = "\n\n[********************LinkedIn API Diagnostics**************************]\n\n";
	if(!is_null($exception)) {
		$log .= "|-> " . $exception->getMessage() . " <-|";
		$debugInfo = $exception->debugInfo;
	} else {
		$debugInfo = $oauth->debugInfo;
	}
	$log .= "\n|-> " . API_CONSUMER_KEY . " <-|"; 
	$log .= "\n|-> " . $debugInfo["sbs"] . " <-|";
	$log .= "\n\n[*****Sent*****]\n";
	$log .= "\n|-> " . str_replace(array("\r\n","\r","\n"), array("\\[rn]","\\[r]","\\[n]"), $debugInfo["headers_sent"]) . " <-|";
	$log .= "\n|-> " . str_replace(array("\r\n","\r","\n"), array("\\[rn]","\\[r]","\\[n]"), $debugInfo["body_sent"]) . " <-|";
	$log .= "\n\n[*****Received*****]\n";
	$log .= "\n|-> " . str_replace(array("\r\n","\r","\n"), array("\\[rn]","\\[r]","\\[n]"), $debugInfo["headers_recv"]) . " <-|";
	$log .= "\n|-> " . str_replace(array("\r\n","\r","\n"), array("\\[rn]","\\[r]","\\[n]"), $debugInfo["body_recv"]) . " <-|"; 
	$log .= "\n\n[******************End LinkedIn API Diagnostics************************]\n\n";
	error_log($log);
}

// Now demonstrate a request failing
print_line("\n********Intentionally causing an error to occur to demonstrate error logger********");
$api_url = "https://api.linkedin.com/v1/people/FOOBARBAZ";

// in helpers.php, we defined a global exception handler
// but still wrapping this in a try/catch to demonstrate
// the logger functionality all in one place
try {
	$oauth->fetch($api_url, null, OAUTH_HTTP_METHOD_GET);
} catch(Exception $e) {
	log_diagnostics_info($oauth, $e);
}
