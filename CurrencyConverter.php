#!/usr/bin/php
<?php

// Object-oriented code from the PHP manual

// SimpleXML code based on http://paulstamatiou.com/how-to-parse-xml-with-php5

class CurrencyConverter
{
	// property declarations
	
	// private database handle goes here
	
    // method declarations
    
	function __construct() {
		
		// Initialize db connection here
	}

	public function update_rates() {
		$request_url = 'http://toolserver.org/~kaldari/rates.xml';
	
		// Read current rates from XML API
		try {
			$xml = simplexml_load_file($request_url); 
		} catch (Exception $e) {
    		echo 'Caught exception: ',  $e->getMessage(), "\n";
		}
	
		// parse list of rates	
		
		// update database entry for each currency
		
		// return success or failure	
	}
	
	public function lookup_rate($curname) {
		
		// query database
		
		return $rate;
		
	}
	
	function parse_currency($input_string) {
		// split the currency string into currency name and value
		// Do a simple regex here.
		return array ($curname, $curvalue);
	}
	
	function join_currency($curname, $curvalue) {
		// join the currency name and value into one string
		return $output_string;
	}
	
	public function convert($input_string) {
		
		// Convert one foreign amount to USD
		
		list ($curname, $curvalue) = parse_currency($input_string);
		
		$rate = $self->lookup_rate($curname);
		
		return join_currency("USD", $rate*$curvalue);
	}
	
	public function convert_array(array $input_array) { // Using type hinting to require array
	
		// Convert an array of foreign amounts to USD, returning an array
		
		$output_array = array();
		foreach ($input_array as $item)	{
			array_push($output_array, $self->convert($item));
		}
		return $output_array;
	}
	
}

$conv = new CurrencyConverter(); 

// Here I'll test the module on the command line. 
// Does php have anything like python's if __name__ == "__main__" construct?

echo "hello world\n";

?>
