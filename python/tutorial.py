# Copyright 2011 LinkedIn Corporation

#   Licensed under the Apache License, Version 2.0 (the "License");
#   you may not use this file except in compliance with the License.
#   You may obtain a copy of the License at

#       http://www.apache.org/licenses/LICENSE-2.0

#   Unless required by applicable law or agreed to in writing, software
#   distributed under the License is distributed on an "AS IS" BASIS,
#   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#   See the License for the specific language governing permissions and
#   limitations under the License.

import oauth2 as oauth
import httplib2
import time, os, simplejson
import urlparse
import BaseHTTPServer 
from xml.etree import ElementTree as ET

# Need to install:
# ElementTree for XML parsing: 
#		easy_install ElementTree
#		http://effbot.org/downloads#elementtree
# simplejson for JSON parsing: 
#		easy_install simplejson
#		https://github.com/simplejson/simplejson
 
consumer_key    =   'xxxxxxxxxxxxxx'
consumer_secret =   'xxxxxxxxxxxxxx'
request_token_url = 'https://api.linkedin.com/uas/oauth/requestToken'
access_token_url =  'https://api.linkedin.com/uas/oauth/accessToken'
authorize_url =     'https://api.linkedin.com/uas/oauth/authorize'

config_file   = '.service.dat'
xml_file      = '.xml.dat'

http_status_print = BaseHTTPServer.BaseHTTPRequestHandler.responses
 

def get_auth():
	consumer = oauth.Consumer(consumer_key, consumer_secret)
	client = oauth.Client(consumer)

	try:
		filehandle = open(config_file)
		
	except IOError as e:
		filehandle = open(config_file,"w")
		print("We don't have a service.dat file, so we need to get access tokens!");
		content = make_request(client,request_token_url,{},"Failed to fetch request token","POST")
	
		request_token = dict(urlparse.parse_qsl(content))
		print "Go to the following link in your browser:"
		print "%s?oauth_token=%s" % (authorize_url, request_token['oauth_token'])
	 
		oauth_verifier = raw_input('What is the PIN? ')
	 
		token = oauth.Token(request_token['oauth_token'],
		request_token['oauth_token_secret'])
		token.set_verifier(oauth_verifier)
		client = oauth.Client(consumer, token)
	 
		content = make_request(client,access_token_url,{},"Failed to fetch access token","POST")
		
		access_token = dict(urlparse.parse_qsl(content))
	 
		token = oauth.Token(
			key=access_token['oauth_token'],
			secret=access_token['oauth_token_secret'])
	 
		client = oauth.Client(consumer, token)
		simplejson.dump(access_token,filehandle)
	
	else:
		config = simplejson.load(filehandle)
		if ("oauth_token" in config and "oauth_token_secret" in config):
			token = 	oauth.Token(config['oauth_token'],
	    				config['oauth_token_secret'])
			client = oauth.Client(consumer, token)
		else:
			print("We had a .service.dat file, but it didn't contain a token/secret?")
			exit()
	return client

# Simple oauth request wrapper to handle responses and exceptions
def make_request(client,url,request_headers={},error_string="Failed Request",method="GET",body=None):
	if body:
		resp,content = client.request(url, method, headers=request_headers, body=body)
	else:
		resp,content = client.request(url, method, headers=request_headers)
	print resp.status
		
	if resp.status >= 200 and resp.status < 300:
		return content
	elif resp.status >= 500 and resp.status < 600:
		error_string = "Status:\n\tRuh Roh! An application error occured! HTTP 5XX response received."
		log_diagnostic_info(client,url,request_headers,method,body,resp,content,error_string)
		
	else:
		status_codes = {403: "\n** Status:\n\tA 403 response was received. Usually this means you have reached a throttle limit.",
						401: "\n** Status:\n\tA 401 response was received. Usually this means the OAuth signature was bad.",
						405: "\n** Status:\n\tA 405 response was received. Usually this means you used the wrong HTTP method (GET when you should POST, etc).",
						400: "\n** Status:\n\tA 400 response was received. Usually this means your request was formatted incorrectly or you added an unexpected parameter.",
						404: "\n** Status:\n\tA 404 response was received. The resource was not found."}
		if resp.status in status_codes:
			log_diagnostic_info(client,url,request_headers,method,body,resp,content,status_codes[resp.status])
		else:
			log_diagnostic_info(client,url,request_headers,method,body,resp,content,http_status_print[resp.status][1])
	
def get_xml():

	# In python, the easiest XML library prints most elegantly to files 
	# and not strings.  In this case we'll create a temporary file with 
	# the XML if one doesn't already exist
	
	try:
		filehandle = open(xml_file)
	except IOError as e:
		# fields used in all share examples 
		# these would usually be provided by you / the user
		comment_text = "Testing out the LinkedIn REST Share API with XML";
		title_text = "Survey: Social networks top hiring tool - San Francisco Business Times";
		url = "http://sanfrancisco.bizjournals.com/sanfrancisco/stories/2010/06/28/daily34.html";
		image = "http://images.bizjournals.com/travel/cityscapes/thumbs/sm_sanfrancisco.jpg";
		visibility = "anyone";
		
		share_element = ET.Element("share")
		comment = ET.SubElement(share_element,"comment")
		comment.text = comment_text
		
		content_element = ET.SubElement(share_element,"content")
		
		title = ET.SubElement(content_element,"title")
		title.text = title_text
		submitted_url = ET.SubElement(content_element,"submitted-url")
		submitted_url.text = url
		submitted_image_url = ET.SubElement(content_element,"submitted-image-url")
		submitted_image_url.text = image
		
		visibility_element = ET.SubElement(share_element,"visibility")
		code_element = ET.SubElement(visibility_element,"code")
		code_element.text = visibility
		
		tree = ET.ElementTree(share_element)
		tree.write(xml_file,encoding="utf-8",xml_declaration=True)
		filehandle = open(xml_file)	
		
	xml_content = filehandle.read()
	return xml_content

	
def get_json():
	comment_text = "Testing out the LinkedIn REST Share API with JSON";
	title_text = "Survey: Social networks top hiring tool - San Francisco Business Times";
	url = "http://sanfrancisco.bizjournals.com/sanfrancisco/stories/2010/06/28/daily34.html";
	image = "http://images.bizjournals.com/travel/cityscapes/thumbs/sm_sanfrancisco.jpg";
	visibility = "anyone";
	
	share_object = {
					"comment":comment_text,
					"content": {
						"title":title_text,
						"submitted_url":url,
						"submitted_image_url":image
					},
					"visibility": {
						"code":visibility
					}
	}
	
	json_content = simplejson.dumps(share_object)
	return json_content

def log_diagnostic_info(client,url,request_headers,method,body,resp,content,error_string):
	# we build up a string, then log it, as multiple calls to () are not guaranteed to be contiguous
	log = "\n\n[********************LinkedIn API Diagnostics**************************]\n\n"
	log += "\n|-> Status: " + str(resp.status) + " <-|"
	log += "\n|-> " + simplejson.dumps(error_string) + " <-|"
	
	log += "\n|-> Key: " + consumer_key + " <-|"
	log += "\n|-> URL: " + url + " <-|"
	log += "\n\n[*****Sent*****]\n";
	log += "\n|-> Headers:" + simplejson.dumps(request_headers) + " <-|"
	if (body):
		log += "\n|-> Body: " + body + " <-|"
	log += "\n|-> Method: " + method + " <-|"
	log += "\n\n[*****Received*****]\n"
	log += "\n|-> Response object: " + simplejson.dumps(resp) + " <-|"
	log += "\n|-> Content: " + content + " <-|";
	log += "\n\n[******************End LinkedIn API Diagnostics************************]\n\n"
	print log



if __name__ == "__main__":
	# Get authorization set up and create the OAuth client
	client = get_auth()
	
	##############################
	# Getting Data from LinkedIn #
	##############################
	
	# Simple profile call
	print "\n********A basic user profile call********"
	response = make_request(client,"http://api.linkedin.com/v1/people/~")
	print response
	
	# Simple profile call, returned in JSON
	print "\n********Get the profile in JSON********"
	response = make_request(client,"http://api.linkedin.com/v1/people/~",{"x-li-format":'json'})
	print response
	
	# Simple profile call, returned in JSON, using query param instead of header
	print "\n********Get the profile in JSON********"
	response = make_request(client,"http://api.linkedin.com/v1/people/~?format=json")
	print response
	
	# Simple connections call
	print "\n********Get the profile in JSON using query parameter********"
	response = make_request(client,"http://api.linkedin.com/v1/people/~/connections")
	print response
	
	# Simple connections call
	print "\n********Get only 10 connections - using parameters********"
	response = make_request(client,"http://api.linkedin.com/v1/people/~/connections?count=10")
	print response
	
	# Get network updates that are shares or connection updates
	print "\n********GET network updates that are CONN and SHAR********"
	response = make_request(client,"http://api.linkedin.com/v1/people/~/network/updates?type=SHAR&type=CONN")
	print response
	
	# People search using facets and encoding input parameters
	print "\n********People Search using facets and Encoding input parameters i.e. UTF8********"
	response = make_request(client,"http://api.linkedin.com/v1/people-search:(people:(first-name,last-name,headline),facets:(code,buckets))?title=D%C3%A9veloppeur&facet=industry,4&facets=location,industry")
	print response
	
	############################
	# Writing Data to LinkedIn #
	############################
	
	xml_content = get_xml()
	json_content = get_json()
	
	print "\n********Write to the share - using XML********"
	api_url = "http://api.linkedin.com/v1/people/~/shares";
	#response = make_request(client,api_url,{"Content-Type":"text/xml"},"Failed to post share","POST",xml_content)
	
	print "\n********Write to the share - using XML, also to twitter********"
	api_url = "http://api.linkedin.com/v1/people/~/shares?twitter-post=true";
	#response = make_request(client,api_url,{"Content-Type":"text/xml"},"Failed to post share","POST",xml_content)
	
	print "\n********Write to the share - using JSON********"
	api_url = "http://api.linkedin.com/v1/people/~/shares";
	response = make_request(client,api_url,{'Content-Type':'application/json'},"Failed to post share","POST",json_content)
	exit();
	
	############################
	# Field Selectors          #
	############################


	print "\n********A basic user profile call with field selectors********"
	api_url = "http://api.linkedin.com/v1/people/~:(first-name,last-name,positions)"
	response = make_request(client,api_url)
	print response


	print "\n********A basic user profile call with field selectors going into a subresource********"
	api_url = "http://api.linkedin.com/v1/people/~:(first-name,last-name,positions:(company:(name)))"
	response = make_request(client,api_url)
	print response


	print "\n********A basic user profile call into a subresource return data in JSON********"
	api_url = "https://api.linkedin.com/v1/people/~/connections:(first-name,last-name,headline)?format=json"
	response = make_request(client,api_url)
	print response


	print "\n********A more complicated example using postings into groups********"
	api_url = "http://api.linkedin.com/v1/groups/3297124/posts:(id,category,creator:(id,first-name,last-name),title,summary,creation-timestamp,site-group-post-url,comments,likes)"
	response = make_request(client,api_url)
	print response

    ####################################################################################
	# Understanding the response, creating logging and response headers                #
	####################################################################################

	print "\n********A basic user profile call and response dissected********"
	api_url = "https://api.linkedin.com/v1/people/~";
	resp,content = client.request(api_url)

	print "\n** Response Headers:\n%s\n" % (resp) 
