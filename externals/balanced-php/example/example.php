<?php

require('vendor/autoload.php');

Httpful\Bootstrap::init();
RESTful\Bootstrap::init();
Balanced\Bootstrap::init();

print "create our new api key\n";
$key = new Balanced\APIKey();
$key->save();
print "Our secret is " . $key->secret . "\n";

print "configure with our secret " . $key->secret . "\n";
Balanced\Settings::$api_key = $key->secret;

print "create our marketplace";
$marketplace = new Balanced\Marketplace();
$marketplace->save();

if (Balanced\Merchant::me() == null) {
   throw new Exception("Balanced\Merchant::me() should not be null");
}

print "What's my merchant? Easy: Balanced\Merchant::me(): " . Balanced\Merchant::me()->uri . "\n";

if (Balanced\Marketplace::mine() == null) {
   throw new Exception("Balanced\Marketplace::mine() should never be null");
}

print "What's my marketplace? Easy: Balanced\Marketplace::mine(): " .Balanced\Marketplace::mine()->uri . "\n";

print "My marketplace's name is " . $marketplace->name . "\n";
print "Changing it to TestFooey\n";
$marketplace->name = "TestFooey";
$marketplace->save();
print "My marketplace name is now " . $marketplace->name . "\n";

if ($marketplace->name != "TestFooey") {
   throw new Exception("Marketplace name is NOT TestFooey");
}

print "Cool, let's create a card\n";
$card = $marketplace->cards->create(array(
      "card_number" => "5105105105105100", 
      "expiration_month" => "12",
      "expiration_year" => "2015"
));

print "Our card: " . $card->uri . "\n";

print "Create out **buyer** account\n";
$buyer = $marketplace->createBuyer("buyer@example.org", $card->uri);
print "our buyer account: " . $buyer->uri . "\n";

print "hold some amount of funds on the buyer, let's say $15\n";
$the_hold = $buyer->hold(1500);

print "ok, no more holds! let's capture it (for the full amount)\n";
$debit = $the_hold->capture();

print "hmm, ho much money do i have in escrow? it should equal the debit amount\n";
$marketplace = Balanced\Marketplace::mine();
if ($marketplace->in_escrow != 1500) {
   throw new Exception("1500 is not in escrow! This is wrong");
}
print "I have " . $marketplace->in_escrow . " in escrow!\n";

print "Cool. now let me refund the full amount";
$refund = $debit->refund();

print "ok, we have a merchant that's signing up, let's create an account for them first, let's create their bank account\n";

$bank_account = $marketplace->createBankAccount("Jack Q Merchant",
	      "123123123", /* account_number */
	      "123123123"  /* bank_code (routing number is USA)*/
	      );

$identity = array(
	  "type" => "person",
	  "name" => "Billy Jones",
	  "street_address" => "801 High St",
	  "postal_code" => "94301",
	  "country" => "USA",
	  "dob" => "1979-02",
	  "phone_number" => "+16505551234"
);

$merchant = $marketplace->createMerchant('merchant@example.org', 
	  $identity,
	  $bank_account->uri
);

print "our buyer is interested in buying something for $130\n";
$another_debit = $buyer->debit(13000, "MARKETPLACE.COM");

print "let's credit our merchant $110\n";
$credit = $merchant->credit(11000, "Buyer purchase something on Marketplace.com");

print "let's assume the marketplace charges 15%, so it earned $20\n";
$mp_credit = $marketplace->owner_account->credit(2000,
	   "Commission from MARKETPLACE.COM");

print "ok, let's invalidate the card used so it cannot be used again\n";
$card->is_valid = false;
$card->save();

print "how do we look up an existing object from the URI?\n";
$the_buyer = Balanced\Account::get($buyer->uri);
print "we got the buyer " . $the_buyer->email_address . "\n";

$the_debit = Balanced\Debit::get($debit->uri);
print "we got the debit: " . $the_debit->uri . "\n";

$the_credit = Balanced\Credit::get($credit->uri);
print "we got the credit: " . $the_credit->uri . "\n";

print "and there you have it :)\n";

?>