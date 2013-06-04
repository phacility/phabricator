<?php
require(__DIR__ . '/../bootstrap.php');

$query = urlencode('#PHP');
$response = \Httpful\Request::get("http://search.twitter.com/search.json?q=$query")->send();

if (!$response->hasErrors()) {
    foreach ($response->body->results as $tweet) {
        echo "@{$tweet->from_user} tweets \"{$tweet->text}\"\n";
    }
} else {
    echo "Uh oh.  Twitter gave us the old {$response->code} status.\n";
}
