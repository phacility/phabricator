<?php
//
// Learn how to authenticate a bank account so you can debit with it.
//

require(__DIR__ . '/vendor/autoload.php');

Httpful\Bootstrap::init();
RESTful\Bootstrap::init();
Balanced\Bootstrap::init();

// create a new marketplace
$key = new Balanced\APIKey();
$key->save();
Balanced\Settings::$api_key = $key->secret;
$marketplace = new Balanced\Marketplace();
$marketplace->save();

// create a bank account
$bank_account = $marketplace->createBankAccount("Jack Q Merchant",
    "123123123",
    "123123123"
);
$buyer = $marketplace->createAccount("buyer@example.org");
$buyer->addBankAccount($bank_account);

print("you can't debit from a bank account until you verify it\n");
try {
    $buyer->debit(100);
} catch (Exception $e) {
    printf("Debit failed, %s\n", $e->getMessage());
}

// authenticate
$verification = $bank_account->verify();

try {
    $verification->confirm(1, 2);
} catch (Balanced\Errors\BankAccountVerificationFailure $e) {
    printf('Authentication error , %s\n', $e->getMessage());
    print("PROTIP: for TEST bank accounts the valid amount is always 1 and 1\n");

}

$verification->confirm(1, 1);

$debit = $buyer->debit(100);
printf("debited the bank account %s for %d cents\n",
    $debit->source->uri,
    $debit->amount
);
print("and there you have it");

?>
