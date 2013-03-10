#!/usr/bin/php
<?php

// Object-oriented code from the PHP manual

// SimpleXML code based on http://paulstamatiou.com/how-to-parse-xml-with-php5

// PHP-MySQL code based on http://www.w3schools.com/php/php_mysql_connect.asp and the PHP manual

class CurrencyConverter
{
	// property declarations
	
	// private database handle goes here
	
	private $con;
	
	// For debugging. Error messages will still print if DEBUG = false.
	// If this code were generating a web page I would send all messages
	// to an error log instead of echo.
	
	const DEBUG = FALSE;
	
    // method declarations
    
	function __construct() {
		
		// Initialize db connection here
		try {
			$mysqli = new mysqli("127.0.0.1","phptask","phptaskpw","phptask");
		} catch (Exception $e) {
			echo 'ERROR: Caught exception: ',  $e->getMessage(), PHP_EOL;
		}
		if ($mysqli->connect_errno) {
			echo "ERROR: Failed to connect to MySQL: ", $mysqli->connect_error, PHP_EOL;
			// call exit(); here?
		}
		
		$this->con = $mysqli;	
	}
	
	public function update_rate($curname, $currate) {
		
		// Update the rate for this currency.
		// There are several improvements I could make here:
		// Probably I could update all the currencies at once, instead of having a query for each one.
		// Also, these nested if statements are clumsy 
		if ($this->con->query("UPDATE exchange_rates SET currate = " . $currate . " WHERE curname = '" . $curname . "'")) {
			if ($this->con->affected_rows==0) {
				// Problem: this keeps duplicating rows. Need to make curname a primary key
				$this->con->query("INSERT INTO exchange_rates (curname, currate) VALUES ('" . $curname . "'," . $currate . ")");
				if ($this->con->affected_rows==0) {
					if (self::DEBUG) echo "DEBUG: did not insert", PHP_EOL;
				} else {
					if (self::DEBUG) echo "DEBUG: successful insert", PHP_EOL;
				}
			} else {
				if (self::DEBUG) echo "DEBUG: successful update", PHP_EOL;
			}
			return TRUE;
		} else {
			echo "ERROR: Failed to update database: ", $this->con->error, PHP_EOL;
			return FALSE;
		}
		
	}

	public function update_rates() {
		$request_url = 'http://toolserver.org/~kaldari/rates.xml';
	
		// Read current rates from XML API
		try {
			$xml = simplexml_load_file($request_url); 
		} catch (Exception $e) {
    		echo "ERROR: Caught exception: ",  $e->getMessage(), PHP_EOL;
		}
		
		// parse list of rates	
		
		foreach($xml->conversion as $conversion) {
			
			$curname = $conversion->currency;
			$currate = $conversion->rate;
			if (self::DEBUG) echo "DEBUG: updating rate ", $curname, " ", $currate, PHP_EOL;
			
			// update database entry for each currency
		
			$this->update_rate($curname, $currate);
		}
	
		// return success or failure	
	}
	
	public function lookup_rate($curname) {
		if (self::DEBUG) echo "DEBUG: lookup rate for ", $curname, PHP_EOL;
		
		if ($res = $this->con->query("SELECT currate FROM exchange_rates WHERE curname = '" . $curname . "'")) {
			if ($res->num_rows==0) {
				echo "ERROR: select got zero rows", PHP_EOL;	
				$res->close(); // Free result set
				return FALSE;
			} else {
				$row = $res->fetch_assoc();
				$rate = $row[currate];
				$res->close(); // Free result set
				return $rate;
			}
		} else {
			echo "ERROR: Failed to query database: " . $this->con->error;
			return FALSE;
		}
	}
	
	private function parse_currency($input_string) {
		// split the currency string into currency name and value
		
		if (self::DEBUG) echo "DEBUG: parsing ", $input_string, PHP_EOL;
		
		// Could do a simple split here using preg_split()...
		// ...but to be safe I will only match specifically what I want.
		
		// This regex matches exactly three "word" characters (which represent
		// the currency code... possibly I should restrict to only [A-Z]) 
		// followed by one or more whitespace characters, which are in turn
		// followed by one or more digits or "."s. So this will match any rates
		// whether they are integers or decimal (although it would incorrectly
		// match any strings of numbers that include more than one decimal point)
		if (preg_match("/(\w\w\w)\s+([\d\.]+)/",$input_string, $matches)) {
			$curname = $matches[1]; // start from 1... 0 returns all matches
			$curvalue = $matches[2];
			if (self::DEBUG) echo "DEBUG: regex matched name: ", $curname, " value: ", $curvalue, PHP_EOL;
			return array ($curname, $curvalue);
		} else {
			if (self::DEBUG) echo "DEBUG: regex did not match", PHP_EOL;
			return FALSE;
		}
	}
	
	private function join_currency($curname, $curvalue) {
		// join the currency name and value into one string
		$output_string = $curname . " " . $curvalue;
		return $output_string;
	}
	
	public function convert($input_string) {
		
		// Convert one foreign amount to USD
		
		list ($curname, $curvalue) = $this->parse_currency($input_string);
		if (self::DEBUG) echo "DEBUG: parse result: ", $curname, " ", $curvalue, PHP_EOL;
		
		$rate = $this->lookup_rate($curname);
		if (self::DEBUG) echo "DEBUG: rate: ", $rate, PHP_EOL;
		
		$usd = $rate * $curvalue;	
		if (self::DEBUG) echo "DEBUG: converted to usd: ", $usd, PHP_EOL;
		
		return $this->join_currency("USD", $usd);
	}
	
	public function convert_array(array $input_array) { // Using type hinting to require array
	
		// Convert an array of foreign amounts to USD, returning an array
		
		$output_array = array();
		foreach ($input_array as $item)	{
			array_push($output_array, $this->convert($item));
		}
		return $output_array;
	}
	
}

// Here I'll test the module on the command line. 
// Does php have anything like python's if __name__ == "__main__" construct?

$conv = new CurrencyConverter(); 

$conv->update_rates();

echo "testing conversion of one amount at a time: ", PHP_EOL;

$input = 'JPY 5000';
echo "input:  ", $input, PHP_EOL;
echo "output: ", $conv->convert($input), PHP_EOL;

echo "testing conversion of an array of amounts: ", PHP_EOL;

$input = array('JPY 5000', 'CZK 62.5');
echo "input array:  ";
foreach ($input as $item) {
	echo $item, " ";
}
echo PHP_EOL;

echo "output array: ";
foreach ($conv->convert_array($input) as $item) {
	echo $item, " ";
}
echo PHP_EOL;

?>
