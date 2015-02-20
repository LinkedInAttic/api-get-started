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
package com.linkedin.sample;

import org.dom4j.Document;
import org.dom4j.DocumentHelper;
import org.dom4j.Element;
import org.json.simple.JSONObject;
import org.json.simple.JSONValue;
import org.scribe.builder.ServiceBuilder;
import org.scribe.builder.api.LinkedInApi;
import org.scribe.model.OAuthRequest;
import org.scribe.model.Response;
import org.scribe.model.Token;
import org.scribe.model.Verb;
import org.scribe.oauth.OAuthService;

import java.io.*;
import java.util.HashMap;
import java.util.Map;


public class Main {

    private static String API_KEY = "xxxxxxxxxxxxxx";
    private static String API_SECRET = "xxxxxxxxxxxxxx";

    public static void main(String[] args) {


        /*
        we need a OAuthService to handle authentication and the subsequent calls.
        Since we are going to use the REST APIs we need to generate a request token as the first step in the call.
        Once we get an access toke we can continue to use that until the API key changes or auth is revoked.
        Therefore, to make this sample easier to re-use we serialize the AuthHandler (which stores the access token) to
        disk and then reuse it.

        When you first run this code please insure that you fill in the API_KEY and API_SECRET above with your own
        credentials and if there is a service.dat file in the code please delete it.

         */

        //The Access Token is used in all Data calls to the APIs - it basically says our application has been given access
        //to the approved information in LinkedIn
        Token accessToken = null;

        //Using the Scribe library we enter the information needed to begin the chain of Oauth2 calls.
        OAuthService service = new ServiceBuilder()
                                .provider(LinkedInApi.class)
                                .apiKey(API_KEY)
                                .apiSecret(API_SECRET)
                                .build();

        /*************************************
         * This first piece of code handles all the pieces needed to be granted access to make a data call
         */

        try{
            File file = new File("service.dat");

            if(file.exists()){
                //if the file exists we assume it has the AuthHandler in it - which in turn contains the Access Token
                ObjectInputStream inputStream = new ObjectInputStream(new FileInputStream(file));
                AuthHandler authHandler = (AuthHandler) inputStream.readObject();
                accessToken = authHandler.getAccessToken();
            } else {
                System.out.println("There is no stored Access token we need to make one");
                //In the constructor the AuthHandler goes through the chain of calls to create an Access Token
                AuthHandler authHandler = new AuthHandler(service);
                ObjectOutputStream outputStream = new ObjectOutputStream(new FileOutputStream("service.dat"));
                outputStream.writeObject( authHandler);
                outputStream.close();
                accessToken = authHandler.getAccessToken();
            }

        }catch (Exception e){
            System.out.println("Threw an exception when serializing: " + e.getClass() + " :: " + e.getMessage());
        }

        /*
         * We are all done getting access - time to get busy getting data
         *************************************/

        /**************************
         *
         * Querying the LinkedIn API
         *
         **************************/

        System.out.println();
        System.out.println("********A basic user profile call********");
        //The ~ means yourself - so this should return the basic default information for your profile in XML format
        //https://developer.linkedin.com/documents/profile-api
        String url = "http://api.linkedin.com/v1/people/~";
        OAuthRequest request = new OAuthRequest(Verb.GET, url);
        service.signRequest(accessToken, request);
        Response response = request.send();
        System.out.println(response.getBody());
        System.out.println();System.out.println();

        System.out.println("********Get the profile in JSON********");
        //This basic call profile in JSON format
        //You can read more about JSON here http://json.org
        url = "http://api.linkedin.com/v1/people/~";
        request = new OAuthRequest(Verb.GET, url);
        request.addHeader("x-li-format", "json");
        service.signRequest(accessToken, request);
        response = request.send();
        System.out.println(response.getBody());
        System.out.println();System.out.println();

        System.out.println("********Get the profile in JSON using query parameter********");
        //This basic call profile in JSON format. Please note the call above is the preferred method.
        //You can read more about JSON here http://json.org
        url = "http://api.linkedin.com/v1/people/~";
        request = new OAuthRequest(Verb.GET, url);
        request.addQuerystringParameter("format", "json");
        service.signRequest(accessToken, request);
        response = request.send();
        System.out.println(response.getBody());
        System.out.println();System.out.println();


        System.out.println("********Get my connections - going into a resource********");
        //This basic call gets all your connections each one will be in a person tag with some profile information
        //https://developer.linkedin.com/documents/connections-api
        url = "http://api.linkedin.com/v1/people/~/connections";
        request = new OAuthRequest(Verb.GET, url);
        service.signRequest(accessToken, request);
        response = request.send();
        System.out.println(response.getBody());
        System.out.println();System.out.println();


        System.out.println("********Get only 10 connections - using parameters********");
        //This basic call gets only 10 connections  - each one will be in a person tag with some profile information
        //https://developer.linkedin.com/documents/connections-api
        //more basic about query strings in a URL here http://en.wikipedia.org/wiki/Query_string
        url = "http://api.linkedin.com/v1/people/~/connections";
        request = new OAuthRequest(Verb.GET, url);
        request.addQuerystringParameter("count", "10");
        service.signRequest(accessToken, request);
        response = request.send();
        System.out.println(response.getBody());
        System.out.println();System.out.println();


        System.out.println("********GET network updates that are CONN and SHAR********");
        //This basic call get connection updates from your connections
        //https://developer.linkedin.com/documents/get-network-updates-and-statistics-api
        //specifics on updates  https://developer.linkedin.com/documents/network-update-types

        url = "http://api.linkedin.com/v1/people/~/network/updates";
        request = new OAuthRequest(Verb.GET, url);
        request.addQuerystringParameter("type","SHAR");
        request.addQuerystringParameter("type","CONN");
        service.signRequest(accessToken, request);
        response = request.send();
        System.out.println(response.getBody());
        System.out.println();System.out.println();


        System.out.println("********People Search using facets and Encoding input parameters i.e. UTF8********");
        //This basic call get connection updates from your connections
        //https://developer.linkedin.com/documents/people-search-api#Facets
        //Why doesn't this look like
        //people-search?title=developer&location=fr&industry=4

        //url = "http://api.linkedin.com/v1/people-search?title=D%C3%A9veloppeur&facets=location,industry&facet=location,fr,0";
       url = "http://api.linkedin.com/v1/people-search:(people:(first-name,last-name,headline),facets:(code,buckets))";
        request = new OAuthRequest(Verb.GET, url);
        request.addQuerystringParameter("title", "Développeur");
        request.addQuerystringParameter("facet", "industry,4");
        request.addQuerystringParameter("facets", "location,industry");
        System.out.println(request.getUrl());
        service.signRequest(accessToken, request);
        response = request.send();
        System.out.println(response.getBody());
        System.out.println();System.out.println();

        /////////////////field selectors
        System.out.println("********A basic user profile call with field selectors********");
        //The ~ means yourself - so this should return the basic default information for your profile in XML format
        //https://developer.linkedin.com/documents/field-selectors
        url = "http://api.linkedin.com/v1/people/~:(first-name,last-name,positions)";
        request = new OAuthRequest(Verb.GET, url);
        service.signRequest(accessToken, request);
        response = request.send();
        System.out.println(response.getHeaders().toString());
        System.out.println(response.getBody());
        System.out.println();System.out.println();


        System.out.println("********A basic user profile call with field selectors going into a subresource********");
        //The ~ means yourself - so this should return the basic default information for your profile in XML format
        //https://developer.linkedin.com/documents/field-selectors
        url = "http://api.linkedin.com/v1/people/~:(first-name,last-name,positions:(company:(name)))";
        request = new OAuthRequest(Verb.GET, url);
        service.signRequest(accessToken, request);
        response = request.send();
        System.out.println(response.getHeaders().toString());
        System.out.println(response.getBody());
        System.out.println();System.out.println();


        System.out.println("********A basic user profile call into a subresource return data in JSON********");
        //The ~ means yourself - so this should return the basic default information for your profile
        //https://developer.linkedin.com/documents/field-selectors
        url = "https://api.linkedin.com/v1/people/~/connections:(first-name,last-name,headline)?format=json";
        request = new OAuthRequest(Verb.GET, url);
        service.signRequest(accessToken, request);
        response = request.send();
        System.out.println(response.getHeaders().toString());
        System.out.println(response.getBody());
        System.out.println();System.out.println();

        System.out.println("********A more complicated example using postings into groups********");
        //https://developer.linkedin.com/documents/field-selectors
        //https://developer.linkedin.com/documents/groups-api
        url = "http://api.linkedin.com/v1/groups/3297124/posts:(id,category,creator:(id,first-name,last-name),title,summary,creation-timestamp,site-group-post-url,comments,likes)";
        request = new OAuthRequest(Verb.GET, url);
        service.signRequest(accessToken, request);
        response = request.send();
        System.out.println(response.getHeaders().toString());
        System.out.println(response.getBody());
        System.out.println();System.out.println();



        /**************************
         *
         * Wrting to the LinkedIn API
         *
         **************************/


/*
 * Commented out so we don't write into your LinkedIn/Twitter feed while you are just testing out
 * some code. Uncomment if you'd like to see writes in action.
 * 
 * 
        System.out.println("********Write to the  share - using XML********");
        //This basic shares some basic information on the users activity stream
        //https://developer.linkedin.com/documents/share-api
        url = "http://api.linkedin.com/v1/people/~/shares";
        request = new OAuthRequest(Verb.POST, url);
        request.addHeader("Content-Type", "text/xml");
        //Make an XML document
        Document doc = DocumentHelper.createDocument();
        Element share = doc.addElement("share");
        share.addElement("comment").addText("Guess who is testing the LinkedIn REST APIs");
        Element content = share.addElement("content");
        content.addElement("title").addText("A title for your share");
        content.addElement("submitted-url").addText("http://developer.linkedin.com");
        share.addElement("visibility").addElement("code").addText("anyone");
        request.addPayload(doc.asXML());
        service.signRequest(accessToken, request);
        response = request.send();
        //there is no body just a header
        System.out.println(response.getBody());
        System.out.println(response.getHeaders().toString());
        System.out.println();System.out.println();


        System.out.println("********Write to the  share and to Twitter - using XML********");
        //This basic shares some basic information on the users activity stream
        //https://developer.linkedin.com/documents/share-api
        url = "http://api.linkedin.com/v1/people/~/shares";
        request = new OAuthRequest(Verb.POST, url);
        request.addQuerystringParameter("twitter-post","true");
        request.addHeader("Content-Type", "text/xml");
        //Make an XML document
        doc = DocumentHelper.createDocument();
        share = doc.addElement("share");
        share.addElement("comment").addText("Guess who is testing the LinkedIn REST APIs and sending to twitter");
        content = share.addElement("content");
        content.addElement("title").addText("A title for your share");
        content.addElement("submitted-url").addText("http://developer.linkedin.com");
        share.addElement("visibility").addElement("code").addText("anyone");
        request.addPayload(doc.asXML());
        service.signRequest(accessToken, request);
        response = request.send();
        //there is no body just a header
        System.out.println(response.getBody());
        System.out.println(response.getHeaders().toString());
        System.out.println();System.out.println();





        System.out.println("********Write to the  share and to twitter - using JSON ********");
        //This basic shares some basic information on the users activity stream
        //https://developer.linkedin.com/documents/share-api
        //NOTE - a good troubleshooting step is to validate your JSON on jsonlint.org
        url = "http://api.linkedin.com/v1/people/~/shares";
        request = new OAuthRequest(Verb.POST, url);
        //set the headers to the server knows what we are sending
        request.addHeader("Content-Type", "application/json");
        request.addHeader("x-li-format", "json");
        //make the json payload using json-simple
        Map<String, Object> jsonMap = new HashMap<String, Object>();
        jsonMap.put("comment", "Posting from the API using JSON");
        JSONObject contentObject = new JSONObject();
        contentObject.put("title", "This is a another test post");
        contentObject.put("submitted-url","http://www.linkedin.com");
        contentObject.put("submitted-image-url", "http://press.linkedin.com/sites/all/themes/presslinkedin/images/LinkedIn_WebLogo_LowResExample.jpg");
        jsonMap.put("content", contentObject);
        JSONObject visibilityObject = new JSONObject();
        visibilityObject.put("code", "anyone");
        jsonMap.put("visibility", visibilityObject);
        request.addPayload(JSONValue.toJSONString(jsonMap));
        service.signRequest(accessToken, request);
        response = request.send();
        //again no body - just headers
        System.out.println(response.getBody());
        System.out.println(response.getHeaders().toString());
        System.out.println();System.out.println();
*/

        /**************************
         *
         * Understanding the response, creating logging, request and response headers
         *
         **************************/

        System.out.println();
        System.out.println("********A basic user profile call and response dissected********");
        //This sample is mostly to help you debug and understand some of the scaffolding around the request-response cycle
        //https://developer.linkedin.com/documents/debugging-api-calls
        url = "https://api.linkedin.com/v1/people/~";
        request = new OAuthRequest(Verb.GET, url);
        service.signRequest(accessToken, request);
        response = request.send();
        //get all the headers
        System.out.println("Request headers: " + request.getHeaders().toString());
        System.out.println("Response headers: " + response.getHeaders().toString());
        //url requested
        System.out.println("Original location is: " + request.getHeaders().get("content-location"));
        //Date of response
        System.out.println("The datetime of the response is: " + response.getHeader("Date"));
        //the format of the response
        System.out.println("Format is: " + response.getHeader("x-li-format"));
        //Content-type of the response
        System.out.println("Content type is: " + response.getHeader("Content-Type") + "\n\n");

        //get the HTTP response code - such as 200 or 404
        int responseNumber = response.getCode();

        if(responseNumber >= 199 && responseNumber < 300){
            System.out.println("HOORAY IT WORKED!!");
            System.out.println(response.getBody());
        } else if (responseNumber >= 500 && responseNumber < 600){
            //you could actually raise an exception here in your own code
            System.out.println("Ruh Roh application error of type 500: " + responseNumber);
            System.out.println(response.getBody());
        } else if (responseNumber == 403){
            System.out.println("A 403 was returned which usually means you have reached a throttle limit");
        } else if (responseNumber == 401){
            System.out.println("A 401 was returned which is a Oauth signature error");
            System.out.println(response.getBody());
        } else if (responseNumber == 405){
            System.out.println("A 405 response was received. Usually this means you used the wrong HTTP method (GET when you should POST, etc).");
        }else {
            System.out.println("We got a different response that we should add to the list: " + responseNumber + " and report it in the forums");
            System.out.println(response.getBody());
        }
        System.out.println();System.out.println();


        System.out.println("********A basic error logging function********");
        // Now demonstrate how to make a logging function which provides us the info we need to
        // properly help debug issues. Please use the logged block from here when requesting
        // help in the forums.
        url = "https://api.linkedin.com/v1/people/FOOBARBAZ";
        request = new OAuthRequest(Verb.GET, url);
        service.signRequest(accessToken, request);
        response = request.send();

        responseNumber = response.getCode();

        if(responseNumber < 200 || responseNumber >= 300){
            logDiagnostics(request, response);
        } else {
            System.out.println("You were supposed to submit a bad request");
        }

        System.out.println("******Finished******");

    }

    private static void logDiagnostics(OAuthRequest request, Response response){
        System.out.println("\n\n[********************LinkedIn API Diagnostics**************************]\n");
        System.out.println("Key: |-> " + API_KEY + " <-|");
        System.out.println("\n|-> [******Sent*****] <-|");
        System.out.println("Headers: |-> " + request.getHeaders().toString() + " <-|");
        System.out.println("URL: |-> " + request.getUrl() + " <-|");
        System.out.println("Query Params: |-> " + request.getQueryStringParams().toString() + " <-|");
        System.out.println("Body Contents: |-> " + request.getBodyContents() + " <-|");
        System.out.println("\n|-> [*****Received*****] <-|");
        System.out.println("Headers: |-> " + response.getHeaders().toString() + " <-|");
        System.out.println("Body: |-> " + response.getBody() + " <-|");
        System.out.println("\n[******************End LinkedIn API Diagnostics************************]\n\n");
    }


}
