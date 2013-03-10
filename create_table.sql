--
-- Create the exchange rate table
--
-- I will assume we're using ISO 4217 currency codes so a 3 character name will suffice.
--
-- currate is the value of the currency in USD.
-- Simply multiply the foreign amount by currate to get the value in USD.
--

CREATE TABLE exchange_rates 
  (
    curname CHAR(3) PRIMARY KEY,  
    currate FLOAT NOT NULL
  )
;
