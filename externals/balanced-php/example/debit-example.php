<?php

require('vendor/autoload.php');

Httpful\Bootstrap::init();
RESTful\Bootstrap::init();
Balanced\Bootstrap::init();

$API_KEY_SECRET = '5f4db668a5ec11e1b908026ba7e239a9';
Balanced\Settings::$api_key = $API_KEY_SECRET;
$marketplace = Balanced\Marketplace::mine();

print "create a card\n";
$card = $marketplace->cards->create(array(
      "card_number" => "5105105105105100", 
      "expiration_month" => "12",
      "expiration_year" => "2015"
));
print "our card: " . $card->uri . "\n";

print "create a **buyer** account with that card\n";
$buyer = $marketplace->createBuyer(null, $card->uri);
print "our buyer account: " . $buyer->uri . "\n";

print "debit our buyer, let's say $15\n";
try {
    $debit = $buyer->debit(1500);
    print "our buyer debit: " . $debit->uri . "\n";
}
catch (Balanced\Errors\Declined $e) {
    print "oh no, the processor declined the debit!\n";
}
catch (Balanced\Errors\NoFundingSource $e) {
    print "oh no, the buyer has not active funding sources!\n";
}
catch (Balanced\Errors\CannotDebit $e) {
    print "oh no, the buyer has no debitable funding sources!\n";
}

print "and there you have it 8)\n";

?>