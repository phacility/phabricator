<?php

// run this file to test your source install of Balanced

require(__DIR__ . "/httpful/bootstrap.php");
require(__DIR__ . "/restful/bootstrap.php");
require(__DIR__ . "/balanced/bootstrap.php");

echo "[ OK ]\n";
echo "balanced version -- " . \Balanced\Settings::VERSION . " \n";
echo "restful version -- " . \RESTful\Settings::VERSION . " \n";
echo "httpful version -- " . \Httpful\Httpful::VERSION . " \n";
