<?php

// run this file to test your composer install of Balanced

require(__DIR__ . '/vendor/autoload.php');

\Httpful\Bootstrap::init();
\RESTful\Bootstrap::init();
\Balanced\Bootstrap::init();

echo "[ OK ]\n";
echo "balanced version -- " . \Balanced\Settings::VERSION . " \n";
echo "restful version -- " . \RESTful\Settings::VERSION . " \n";
echo "httpful version -- " . \Httpful\Httpful::VERSION . " \n";
