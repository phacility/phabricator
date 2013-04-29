<?
require('vendor/autoload.php');

Httpful\Bootstrap::init();
RESTful\Bootstrap::init();
Balanced\Bootstrap::init();

$key = new Balanced\APIKey();
$key->save();
Balanced\Settings::$api_key = $key->secret;
$marketplace = new Balanced\Marketplace();
$marketplace->save();

$card = $marketplace->cards->create(array(
    "card_number" => "5105105105105100",
    "expiration_month" => "12",
    "expiration_year" => "2015"
    ));

$buyer = $marketplace->createBuyer("buyer@example.com", $card->uri);

$debit = $buyer->debit(1500);
$debit->refund(100);
$debit->refund(100);
$debit->refund(100);

echo $debit->refunds->total() . " refunds" . "\n";

$total = 0;

foreach ($debit->refunds as $r) {
    $total += $r->amount;
    print "refund = " . $r->amount . "\n";
}

print $total . "\n";

# bigger pagination example

print "Create 60 **buyer** with cards accounts\n";

for ($i = 0; $i < 60; $i++) {
    $card = $marketplace->cards->create(array(
    	    "card_number" => "5105105105105100", 
	    "expiration_month" => "12",
	    "expiration_year" => "2015"
    ));
    $buyer = $marketplace->createBuyer("buyer" . $i . "@example.org", $card->uri);
    print '.';
}

print "\n";

$cards = $marketplace->cards;

print $cards->total() . " cards in Marketplace\n";

foreach ($cards as $c) {
	print "card " . $c->uri . "\n";
}

# let's iterate through cards for just a single account

foreach ($buyer->cards as $c) {
	print "buyer's card " . $c->uri . "\n";
}

print "and there you have it :)\n";

        
?>
