<?php
use PHPPE\Core as PHPPE;

// cache test

//check if it's configured and initialized
echo("Users installed: ");
if( !PHPPE::isInst("Users") ) {
	echo("not installed!\n");
	return "SKIP";
} else echo("OK\n");


//everything was ok
return true;
?>