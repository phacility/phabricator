<?php
/*
 * Welcome weary traveller. Sick of polling for state changes? Well today have
 * I got good news for you. Run this example below to see how to get yourself
 * some callback goodness and to understand how events work.
*/
require(__DIR__ . "/vendor/autoload.php");

Httpful\Bootstrap::init();
RESTful\Bootstrap::init();
Balanced\Bootstrap::init();

// create a new marketplace
$key = new Balanced\APIKey();
$key->save();
Balanced\Settings::$api_key = $key->secret;
$marketplace = new Balanced\Marketplace();
$marketplace->save();

// let"s create a requestb.in
$ch = curl_init("http://requestb.in/api/v1/bins");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . 0)
);
$result = json_decode(curl_exec($ch));
$bin_name = $result->name;
$callback_url = "http://requestb.in/" . $bin_name;
$requests_url = "http://requestb.in/api/v1/bins/" . $bin_name . "/requests";

printf("let's create a callback\n");
$marketplace->createCallback($callback_url);

printf("let's create a card and associate it with a new account\n");
$card = $marketplace->cards->create(array(
    "card_number" => "5105105105105100",
    "expiration_month" => "12",
    "expiration_year" => "2015"
));
$buyer = $marketplace->createBuyer("buyer@example.org", $card->uri);

printf("generate a debit (which implicitly creates and captures a hold)\n");
$buyer->debit(100);

foreach ($marketplace->events as $event) {
    printf("this was a %s event, it occurred at %s\n",
        $event->type,
        $event->occurred_at
    );
}

printf("ok, let's check with requestb.in to see if our callbacks fired at %s\n", $callback_url);
printf("we received callbacks, you can view them at http://requestb.in/%s?inspect\n",
    $bin_name
);

?>
