#!/usr/bin/php
<?php
/**
 * CurrencyConverter.php includes the class definition for CurrencyConverter along with testing code
 * 
 * @package CurrencyConverter
 */


/**
 * Convert foreign currencies to USD
 * 
 * Class creates a CurrencyConverter object. The object can retrieve updated conversion rates
 * from an API and store those rates in a MySQL database. It can then convert any foreign 
 * currency amounts into US Dollars (USD).
 * 
 * This class was created as a programming task to demonstrate knowledge of PHP and MySQL.
 * 
 * Most of the code is based on examples from the PHP manual. In addition:
 * SimpleXML code based on http://paulstamatiou.com/how-to-parse-xml-with-php5
 * PHP-MySQL code based on http://www.w3schools.com/php/php_mysql_connect.asp
 * Documentation style based on https://pear.php.net/manual/en/standards.sample.php 
 * (although I have not tested generating documentation based on these docblocks)
 * 
 * @author Alan McConchie <alan.mcconchie@gmail.com>
 */
class CurrencyConverter
{
	// property declarations
	
	/** 
	 * private database handle 
	 * 
	 * @var Object
	 * @access private
	 */
	private $con;

	/**	
	 * debug flag
	 * 
	 * For debugging. Error messages will still print if DEBUG = false.
	 * If this code were generating a web page I would send all messages
	 * to an error log instead of echo.
	 */
	const DEBUG = FALSE;
	
    // method declarations
    
    /**
	 * Constructs the CurrencyConverter object and initializes database connection
	 */
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

	/**
	 * Updates the rate for this currency.
	 * 
	 * There are several improvements I could make here:
	 * Probably I could update all the currencies at once, instead of having a query for each one	.
	 * 
	 * @param string $curname The name (3 letter code, specifically) of the foreign currency
	 * @param float $currate The conversion rate in USD
	 * @return bool whether the update was successful or not
	 */	
	public function update_rate($curname, $currate) {
		
		if ($this->con->query("REPLACE INTO exchange_rates (curname, currate) VALUES ('" . $curname . "'," . $currate . ")")) {
			if (self::DEBUG) echo "DEBUG: successful update", PHP_EOL;
			return TRUE;
		} else {
			echo "ERROR: Failed to update database: ", $this->con->error, PHP_EOL;
			return FALSE;
		}
		
	}

	/**
	 * Updates the rates for all currencies.
	 * 
	 * Accesses the currency API, parses the XML, and then calls update_rate() for each currency
	 * @see update_rate()
	 * @return bool whether the update was successful or not
	 */
	public function update_rates() {
		$request_url = 'http://toolserver.org/~kaldari/rates.xml';
	
		// Read current rates from XML API
		try {
			$xml = simplexml_load_file($request_url); 
		} catch (Exception $e) {
			echo "ERROR: Caught exception: ",  $e->getMessage(), PHP_EOL;
			return FALSE;
		}
		
		// parse list of rates	
		
		foreach($xml->conversion as $conversion) {
			
			$curname = $conversion->currency;
			$currate = $conversion->rate;
			if (self::DEBUG) echo "DEBUG: updating rate ", $curname, " ", $currate, PHP_EOL;
			
			// update database entry for each currency
		
			if (!$this->update_rate($curname, $currate)) {
				return FALSE;
			}
		}

		return TRUE;	
	}

	/**
	 * Looks up the rate for a given currency.
	 * 
	 * Accesses the latest rate stored in the database. Does not check the rates API to see if
	 * the rate has been updated.
	 * @param string the currency name (3 letter code, specifically)
	 * @return float the current rate
	 */	
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
	
	/**
	 * Parses a string including currency name and value
	 * 
	 * This regex matches exactly three "word" characters (which represent
	 * the currency code... possibly I should restrict to only [A-Z]) 
	 * followed by one or more whitespace characters, which are in turn
	 * followed by one or more digits or "."s. So this will match any rates
	 * whether they are integers or decimal (although it would incorrectly
	 * match any strings of numbers that include more than one decimal point)
	 * 
	 * @param string $input_string the string with 3-letter name, some whitespace, then value
	 * @return bool|array on success, returns currency name and value, on failure returns FALSE
	 */
	private function parse_currency($input_string) {
		// split the currency string into currency name and value
		
		if (self::DEBUG) echo "DEBUG: parsing ", $input_string, PHP_EOL;
		
		// Could do a simple split here using preg_split()...
		// ...but to be safe I will only match specifically what I want.
		
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

	/** 
	 * Joins currency name and value into a single string
	 * 
	 * @param string $curname The name (3 letter code, specifically) of the foreign currency
	 * @param float $curvalue The currency amount (could also be an exchange rate)
	 * @return string the name and value concatenated with a space in between
	 */	
	private function join_currency($curname, $curvalue) {
		$output_string = $curname . " " . $curvalue;
		return $output_string;
	}

	/**
	 * Converts one foreign amount to USD
	 * 
	 * @param string $input_string Currency code followed by a space and then the amount
	 * @return bool|string On success returns value in USD in same string format. On failure returns FALSE.
	 */	
	public function convert($input_string) {
		
		list ($curname, $curvalue) = $this->parse_currency($input_string);
		if (self::DEBUG) echo "DEBUG: parse result: ", $curname, " ", $curvalue, PHP_EOL;
		if (($curname == false) || ($curvalue == false)) {
			return FALSE;
		}	
		
		$rate = $this->lookup_rate($curname);
		if (self::DEBUG) echo "DEBUG: rate: ", $rate, PHP_EOL;
		if ($rate == false) {
			return FALSE;
		}
		
		$usd = $rate * $curvalue;	
		if (self::DEBUG) echo "DEBUG: converted to usd: ", $usd, PHP_EOL;
		
		return $this->join_currency("USD", $usd);
	}
	
	/**
	 * Converts an array of foreign amounts to USD, returning an array
	 * 
	 * array values must be of same format as described in convert() method
	 * 
	 * @see convert()
	 * @param array $input_array An array of strings encoding foreign amounts
	 * @return bool|array returns array of strings encoding USD amounts. On failure returns FALSE.
	 */
	public function convert_array(array $input_array) { // Using type hinting to require array
		
		$output_array = array();
		foreach ($input_array as $item)	{
			$result = $this->convert($item);
			if ($result) {
				array_push($output_array, $result);
			} else {
				return FALSE;
			}
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
