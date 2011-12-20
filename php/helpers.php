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

// simple wrapper class which lets us do chaining
// makes example code much cleaner
// feel free to use in production code
class XMLWriter2 
{
	// needed for our simple decorator pattern here
	// as class extension won't work for the purpose
	private $xmlwriter = null;
	
	public function __construct() {
		$this->xmlwriter = new XMLWriter();
	}
	
	// this one needs to be special cased as we don't want to return $this here
	public function outputMemory($flush = TRUE) {
		return $this->xmlwriter->outputMemory($flush);
	}
	
	// Why isn't there a method like this already? :(
	public function addElement($name, $body) {
		$this->xmlwriter->startElement($name);
		$this->xmlwriter->text($body);
		$this->xmlwriter->endElement();
		return $this;
	}
	
	// This handles the "magic" of calling the decorated class's methods
	// But returning $this rather than the pointless bools that it does normally
	public function __call($method, $arguments) {
		if(method_exists($this->xmlwriter, $method)) {
			call_user_func_array(array($this->xmlwriter, $method), $arguments);
			return $this;
		} else {
			throw new Exception("Method undefined!");
		}
	}
}

// This is defined here in case you don't have PECL/http extension
if(!function_exists("http_parse_headers")) {
	function http_parse_headers($headers) {
		$retVal = array();
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $headers));
        foreach( $fields as $field ) {
            if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
                $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
                if( isset($retVal[$match[1]]) ) {
                    $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
                } else {
                    $retVal[$match[1]] = trim($match[2]);
                }
            }
        }
        return $retVal;
	}
}

function print_line($format, $args = array(), $where = STDOUT, $terminated_at_line = FALSE) {
	$args = is_null($args) ? array() : (is_array($args) ? $args : array($args));
	vfprintf($where, $format . "\n", $args);
	if($terminated_at_line) {
		die("\n\nExecution terminated at line #" . $terminated_at_line . "\n\n");
	}
}

function print_response($oauth) {
	print_line("\nAPI URL:\n\thttp://api.linkedin.com%s", substr(array_shift(explode("\n", $oauth->debugInfo["headers_sent"])), strpos($oauth->debugInfo["headers_sent"], " ")+1, -9));
	print_line("Response:\n\t%s", array_shift(explode("\n", $oauth->debugInfo["headers_recv"])));
	print_line("Response Body:\n%s", $oauth->getLastResponse() . " ");
}


// we set a global exception handler which will print out the
// exception plus the debugInfo from OAuth. 
set_exception_handler(create_function('$e', 'log_diagnostics_info($GLOBALS["oauth"], $e);'));