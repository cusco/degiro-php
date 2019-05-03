PHP implementation of DEGIRO api
================================

### Set of functions, that anyone can use to interact with degiro programatically via PHP
(there is no official degiro API)

###### See file functions.php for the list of available functions.
###### See periodic.php as an example of calling specific functions with a specific logic (sell whenever there is a 5% proffit)

______
Instructions
------------
Edit config.php with your credentials

use functions from functions.php

see periodic.php as a example to:
- check if we're logged in
	- else, do log in
- read your portfolio
- for each product in the portfolio, try to sell at a proffit of 5%


run on the command line:
php -q periodic.php


Note: Needs php-curl (apt install php-curl)
