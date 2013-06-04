<?php

namespace Balanced\Test;

\Balanced\Bootstrap::init();
\RESTful\Bootstrap::init();
\Httpful\Bootstrap::init();

use Balanced\Settings;
use Balanced\APIKey;
use Balanced\Marketplace;
use Balanced\Credit;
use Balanced\Debit;
use Balanced\Refund;
use Balanced\Account;
use Balanced\Merchant;
use Balanced\BankAccount;
use Balanced\Card;


/**
 * Suite test cases. These talk to an API server and so make network calls.
 *
 * Environment variables can be used to control client settings:
 *
 * <ul>
 *     <li>$BALANCED_URL_ROOT If set applies to \Balanced\Settings::$url_root.
 *     <li>$BALANCED_API_KEY If set applies to \Balanced\Settings::$api_key.
 * </ul>
 *
 * @group suite
 */
class SuiteTest extends \PHPUnit_Framework_TestCase
{
    static $key,
           $marketplace,
           $email_counter = 0;

    static function _createBuyer($email_address = null, $card = null)
    {
        if ($email_address == null)
            $email_address = sprintf('m+%d@poundpay.com', self::$email_counter++);
        if ($card == null)
            $card = self::_createCard();
        return self::$marketplace->createBuyer(
            $email_address,
            $card->uri,
            array('test#' => 'test_d'),
            'Hobo Joe'
        );
    }

    static function _createCard($account = null)
    {
        $card = self::$marketplace->createCard(
            '123 Fake Street',
            'Jollywood',
            null,
            '90210',
            'khalkhalash',
            '4112344112344113',
            null,
            12,
            2013);
        if ($account != null) {
            $account->addCard($card);
            $card = Card::get($card->uri);
        }
        return $card;
    }

    static function _createBankAccount($account = null)
    {
        $bank_account = self::$marketplace->createBankAccount(
            'Homer Jay',
            '112233a',
            '121042882',
            'checking'
            );
        if ($account != null) {
            $account->addBankAccount($bank_account);
            $bank_account  = $account->bank_accounts[0];
        }
        return $bank_account;
    }

    public static function _createPersonMerchant($email_address = null, $bank_account = null)
    {
        if ($email_address == null)
            $email_address = sprintf('m+%d@poundpay.com', self::$email_counter++);
        if ($bank_account == null)
            $bank_account = self::_createBankAccount();
        $merchant = array(
            'type' => 'person',
            'name' => 'William James',
            'tax_id' => '393-48-3992',
            'street_address' => '167 West 74th Street',
            'postal_code' => '10023',
            'dob' => '1842-01-01',
            'phone_number' => '+16505551234',
            'country_code' => 'USA'
            );
        return self::$marketplace->createMerchant(
            $email_address,
            $merchant,
            $bank_account->uri
            );
    }

    public static function _createBusinessMerchant($email_address = null, $bank_account = null)
    {
        if ($email_address == null)
            $email_address = sprintf('m+%d@poundpay.com', self::$email_counter++);
        if ($bank_account == null)
            $bank_account = self::_createBankAccount();
        $merchant = array(
            'type' => 'business',
            'name' => 'Levain Bakery',
            'tax_id' => '253912384',
            'street_address' => '167 West 74th Street',
            'postal_code' => '10023',
            'phone_number' => '+16505551234',
            'country_code' => 'USA',
            'person' => array(
                'name' => 'William James',
                'tax_id' => '393483992',
                'street_address' => '167 West 74th Street',
                'postal_code' => '10023',
                'dob' => '1842-01-01',
                'phone_number' => '+16505551234',
                'country_code' => 'USA',
                ),
            );
        return self::$marketplace->createMerchant(
            $email_address,
            $merchant,
            $bank_account->uri
            );
    }

    public static function setUpBeforeClass()
    {
        // url root
        $url_root = getenv('BALANCED_URL_ROOT');
        if ($url_root != '') {
            Settings::$url_root = $url_root;
        }
        else
            Settings::$url_root = 'https://api.balancedpayments.com';

        // api key
        $api_key = getenv('BALANCED_API_KEY');
        if ($api_key != '') {
            Settings::$api_key = $api_key;
        }
        else {
            self::$key = new APIKey();
            self::$key->save();
            Settings::$api_key = self::$key->secret;
        }

        // marketplace
        try {
            self::$marketplace = Marketplace::mine();
        }
        catch(\RESTful\Exceptions\NoResultFound $e) {
            self::$marketplace = new Marketplace();
            self::$marketplace->save();
        }
    }

    function testMarketplaceMine()
    {
        $marketplace = Marketplace::mine();
        $this->assertEquals($this::$marketplace->id, $marketplace->id);
    }

    /**
     * @expectedException \RESTful\Exceptions\HTTPError
     */
    function testAnotherMarketplace()
    {
        $marketplace = new Marketplace();
        $marketplace->save();
    }

    /**
     * @expectedException \RESTful\Exceptions\HTTPError
     */
    function testDuplicateEmailAddress()
    {
        self::_createBuyer('dupe@poundpay.com');
        self::_createBuyer('dupe@poundpay.com');
    }

    function testIndexMarketplace()
    {
        $marketplaces = Marketplace::query()->all();
        $this->assertEquals(count($marketplaces), 1);
    }

    function testCreateBuyer()
    {
        self::_createBuyer();
    }

    function testCreateAccountWithoutEmailAddress()
    {
    	self::$marketplace->createAccount();
    }

    function testFindOrCreateAccountByEmailAddress()
    {
    	$account1 = self::$marketplace->createAccount('foc@example.com');
    	$account2 = self::$marketplace->findOrCreateAccountByEmailAddress('foc@example.com');
    	$this->assertEquals($account2->id, $account2->id);
    	$account3 = self::$marketplace->findOrCreateAccountByEmailAddress('foc2@example.com');
    	$this->assertNotEquals($account3->id, $account1->id);
    }

    function testGetBuyer()
    {
        $buyer1 = self::_createBuyer();
        $buyer2 = Account::get($buyer1->uri);
        $this->assertEquals($buyer1->id, $buyer2->id);
    }


    function testMe()
    {
        $marketplace = Marketplace::mine();
        $merchant = Merchant::me();
        $this->assertEquals($marketplace->id, $merchant->marketplace->id);
    }

    function testDebitAndRefundBuyer()
    {
        $buyer = self::_createBuyer();
        $debit = $buyer->debit(
            1000,
            'Softie',
            'something i bought',
            array('hi' => 'bye')
            );
        $refund = $debit->refund(100);
    }

    /**
     * @expectedException \RESTful\Exceptions\HTTPError
     */
    function testDebitZero()
    {
    	$buyer = self::_createBuyer();
    	$debit = $buyer->debit(
    			0,
    			'Softie',
    			'something i bought'
    	);
    }

    function testMultipleRefunds()
    {
        $buyer = self::_createBuyer();
        $debit = $buyer->debit(
            1500,
            'Softie',
            'something tart',
            array('hi' => 'bye'));
        $refunds = array(
            $debit->refund(100),
            $debit->refund(100),
            $debit->refund(100),
            $debit->refund(100));
        $expected_refund_ids = array_map(
            function($x) {
                return $x->id;
            }, $refunds);
        sort($expected_refund_ids);
        $this->assertEquals($debit->refunds->total(), 4);

        // itemization
        $total = 0;
        $refund_ids = array();
        foreach ($debit->refunds as $refund) {
            $total += $refund->amount;
             array_push($refund_ids, $refund->id);
        }
        sort($refund_ids);
        $this->assertEquals($total, 400);
        $this->assertEquals($expected_refund_ids, $refund_ids);

        // pagination
        $total = 0;
        $refund_ids = array();
        foreach ($debit->refunds->paginate() as $page) {
            foreach ($page->items as $refund) {
                $total += $refund->amount;
                array_push($refund_ids, $refund->id);
            }
        }
        sort($refund_ids);
        $this->assertEquals($total, 400);
        $this->assertEquals($expected_refund_ids, $refund_ids);
    }

    function testDebitSource()
    {
        $buyer = self::_createBuyer();
        $card1 = self::_createCard($buyer);
        $card2 = self::_createCard($buyer);

        $credit = $buyer->debit(
            1000,
            'Softie',
            'something i bought'
            );
        $this->assertEquals($credit->source->id, $card2->id);

        $credit = $buyer->debit(
            1000,
            'Softie',
            'something i bought',
            null,
            $card1
            );
        $this->assertEquals($credit->source->id, $card1->id);
    }

    function testDebitOnBehalfOf()
    {
        $buyer = self::_createBuyer();
        $merchant = self::$marketplace->createAccount(null);
        $card1 = self::_createCard($buyer);

        $debit = $buyer->debit(1000, null, null, null, null, $merchant);
        $this->assertEquals($debit->amount, 1000);
        // for now just test the debit succeeds.
        // TODO: once the on_behalf_of actually shows up on the response, test it.
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testDebitOnBehalfOfFailsForBuyer()
    {
        $buyer = self::_createBuyer();
        $card1 = self::_createCard($buyer);
        $debit = $buyer->debit(1000, null, null, null, null, $buyer);
    }

    function testCreateAndVoidHold()
    {
        $buyer = self::_createBuyer();
        $hold = $buyer->hold(1000);
        $this->assertEquals($hold->is_void, false);
        $hold->void();
        $this->assertEquals($hold->is_void, true);
    }

    function testCreateAndCaptureHold()
    {
        $buyer = self::_createBuyer();
        $hold = $buyer->hold(1000);
        $debit = $hold->capture(909);
        $this->assertEquals($debit->account->id, $buyer->id);
        $this->assertEquals($debit->hold->id, $hold->id);
        $this->assertEquals($hold->debit->id, $debit->id);
    }

    function testCreatePersonMerchant()
    {
        $merchant = self::_createPersonMerchant();
    }

    function testCreateBusinessMerchant()
    {
        $merchant = self::_createBusinessMerchant();
    }

    /**
     * @expectedException \RESTful\Exceptions\HTTPError
     */
    function testCreditRequiresNonZeroAmount()
    {
        $buyer = self::_createBuyer();
        $buyer->debit(
            1000,
            'Softie',
            'something i bought'
            );
        $merchant = self::_createBusinessMerchant();
        $merchant->credit(0);
    }

    /**
     * @expectedException \RESTful\Exceptions\HTTPError
     */
    function testCreditMoreThanEscrowBalanceFails()
    {
        $buyer = self::_createBuyer();
        $buyer->credit(
            1000,
            'something i bought',
            null,
            null,
            'Softie'
            );
        $merchant = self::_createBusinessMerchant();
        $merchant->credit(self::$marketplace->in_escrow + 1);
    }

    function testCreditDestiation()
    {
        $buyer = self::_createBuyer();
        $buyer->debit(3000);  # NOTE: build up escrow balance to credit

        $merchant = self::_createPersonMerchant();
        $bank_account1 = self::_createBankAccount($merchant);
        $bank_account2 = self::_createBankAccount($merchant);

        $credit = $merchant->credit(
            1000,
            'something i sold',
            null,
            null,
            'Softie'
            );
        $this->assertEquals($credit->destination->id, $bank_account2->id);

        $credit = $merchant->credit(
            1000,
            'something i sold',
            null,
            $bank_account1,
            'Softie'
            );
        $this->assertEquals($credit->destination->id, $bank_account1->id);
    }

    function testAssociateCard()
    {
        $merchant = self::_createPersonMerchant();
        $card = self::_createCard();
        $merchant->addCard($card->uri);
    }

    function testAssociateBankAccount()
    {
        $merchant = self::_createPersonMerchant();
        $bank_account = self::_createBankAccount();
        $merchant->addBankAccount($bank_account->uri);
    }

    function testCardMasking()
    {
        $card = self::$marketplace->createCard(
            '123 Fake Street',
            'Jollywood',
            null,
            '90210',
            'khalkhalash',
            '4112344112344113',
            '123',
            12,
            2013);
        $this->assertEquals($card->last_four, '4113');
        $this->assertFalse(property_exists($card, 'number'));
    }

    function testBankAccountMasking()
    {
        $bank_account = self::$marketplace->createBankAccount(
            'Homer Jay',
            '112233a',
            '121042882',
            'checking'
            );
        $this->assertEquals($bank_account->last_four, '233a');
        $this->assertEquals($bank_account->account_number, 'xxx233a');
    }

    function testFilteringAndSorting()
    {
        $buyer = self::_createBuyer();
        $debit1 = $buyer->debit(1122, null, null, array('tag' => '1'));
        $debit2 = $buyer->debit(3322, null, null, array('tag' => '1'));
        $debit3 = $buyer->debit(2211, null, null, array('tag' => '2'));

        $getId = function($o) {
            return $o->id;
        };

        $debits = (
            self::$marketplace->debits->query()
            ->filter(Debit::$f->meta->tag->eq('1'))
            ->sort(Debit::$f->created_at->asc())
            ->all());
        $debit_ids = array_map($getId, $debits);
        $this->assertEquals($debit_ids, array($debit1->id, $debit2->id));

        $debits = (
            self::$marketplace->debits->query()
            ->filter(Debit::$f->meta->tag->eq('2'))
            ->all());
        $debit_ids = array_map($getId, $debits);
        $this->assertEquals($debit_ids, array($debit3->id));

        $debits = (
            self::$marketplace->debits->query()
            ->filter(Debit::$f->meta->contains('tag'))
            ->sort(Debit::$f->created_at->asc())
            ->all());
        $debit_ids = array_map($getId, $debits);
        $this->assertEquals($debit_ids, array($debit1->id, $debit2->id, $debit3->id));

        $debits = (
            self::$marketplace->debits->query()
            ->filter(Debit::$f->meta->contains('tag'))
            ->sort(Debit::$f->amount->desc())
            ->all());
        $debit_ids = array_map($getId, $debits);
        $this->assertEquals($debit_ids, array($debit2->id, $debit3->id, $debit1->id));
    }

    function testMerchantIdentityFailure()
    {
        // NOTE: postal_code == '99999' && region == 'EX' triggers identity failure
        $identity = array(
            'type' => 'business',
            'name' => 'Levain Bakery',
            'tax_id' => '253912384',
            'street_address' => '167 West 74th Street',
            'postal_code' => '99999',
            'region' => 'EX',
            'phone_number' => '+16505551234',
            'country_code' => 'USA',
            'person' => array(
                'name' => 'William James',
                'tax_id' => '393483992',
                'street_address' => '167 West 74th Street',
                'postal_code' => '99999',
                'region' => 'EX',
                'dob' => '1842-01-01',
                'phone_number' => '+16505551234',
                'country_code' => 'USA',
                ),
            );

        try {
            self::$marketplace->createMerchant(
                sprintf('m+%d@poundpay.com', self::$email_counter++),
                $identity);
        }
        catch(\RESTful\Exceptions\HTTPError $e) {
            $this->assertEquals($e->response->code, 300);
            $expected = sprintf('https://www.balancedpayments.com/marketplaces/%s/kyc', self::$marketplace->id);
            $this->assertEquals($e->redirect_uri, $expected);
            $this->assertEquals($e->response->headers['Location'], $expected);
            return;
        }
        $this->fail('Expected exception HTTPError not raised.');
    }

    function testInternationalCard()
    {
        $payload = array(
            'card_number' => '4111111111111111',
            'city' => '\xe9\x83\xbd\xe7\x95\x99\xe5\xb8\x82',
            'country_code' => 'JPN',
            'expiration_month' => 12,
            'expiration_year' => 2014,
            'name' => 'Johnny Fresh',
            'postal_code' => '4020054',
            'street_address' => '\xe7\x94\xb0\xe5\x8e\x9f\xef\xbc\x93\xe3\x83\xbc\xef\xbc\x98\xe3\x83\xbc\xef\xbc\x91'
            );
        $card = self::$marketplace->cards->create($payload);
        $this->assertEquals($card->street_address, $payload['street_address']);
    }

    /**
     * @expectedException \RESTful\Exceptions\NoResultFound
     */
    function testAccountWithEmailAddressNotFound()
    {
        self::$marketplace->accounts->query()
            ->filter(Account::$f->email_address->eq('unlikely@address.com'))
            ->one();
    }

    function testDebitACard()
    {
        $buyer = self::_createBuyer();
        $card = self::_createCard($buyer);
        $debit = $card->debit(
            1000,
            'Softie',
            'something i bought',
            array('hi' => 'bye'));
        $this->assertEquals($debit->source->uri, $card->uri);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    function testDebitAnUnassociatedCard()
    {
        $card = self::_createCard();
        $card->debit(1000, 'Softie');
    }

    function testCreditABankAccount()
    {
        $buyer = self::_createBuyer();
        $buyer->debit(101);  # NOTE: build up escrow balance to credit

        $merchant = self::_createPersonMerchant();
        $bank_account = self::_createBankAccount($merchant);
        $credit = $bank_account->credit(55, 'something sour');
        $this->assertEquals($credit->destination->uri, $bank_account->uri);
    }

    function testQuery()
    {
        $buyer = self::_createBuyer();
        $tag = '123123123123';
        $debit1 = $buyer->debit(1122, null, null, array('tag' => $tag));
        $debit2 = $buyer->debit(3322, null, null, array('tag' => $tag));
        $debit3 = $buyer->debit(2211, null, null, array('tag' => $tag));
        $expected_debit_ids = array($debit1->id, $debit2->id, $debit3->id);

        $query = (
            self::$marketplace->debits->query()
            ->filter(Debit::$f->meta->tag->eq($tag))
            ->sort(Debit::$f->created_at->asc())
            ->limit(1));

        $this->assertEquals($query->total(), 3);

        $debit_ids = array();
        foreach ($query as $debits) {
            array_push($debit_ids, $debits->id);
        }
        $this->assertEquals($debit_ids, $expected_debit_ids);

        $debit_ids = array($query[0]->id, $query[1]->id, $query[2]->id);
        $this->assertEquals($debit_ids, $expected_debit_ids);
    }

    function testBuyerPromoteToMerchant()
    {
    	$merchant = array(
            'type' => 'person',
            'name' => 'William James',
            'tax_id' => '393-48-3992',
            'street_address' => '167 West 74th Street',
            'postal_code' => '10023',
            'dob' => '1842-01-01',
            'phone_number' => '+16505551234',
            'country_code' => 'USA'
    	);
    	$buyer = self::_createBuyer();
    	$buyer->promoteToMerchant($merchant);
    }

    function testCreditAccountlessBankAccount()
    {
        $buyer = self::_createBuyer();
        $buyer->debit(101);  # NOTE: build up escrow balance to credit

        $bank_account = self::_createBankAccount();
        $credit = $bank_account->credit(55, 'something sour');
        $this->assertEquals($credit->bank_account->id, $bank_account->id);
        $bank_account = $bank_account->get($bank_account->id);
        $this->assertEquals($bank_account->credits->total(), 1);
    }

    function testCreditUnstoredBankAccount()
    {
        $buyer = self::_createBuyer();
        $buyer->debit(101);  # NOTE: build up escrow balance to credit

        $credit = Credit::bankAccount(
            55,
            array(
                'name' => 'Homer Jay',
                'account_number' => '112233a',
                'routing_number' => '121042882',
                'type' => 'checking',
            ),
            'something sour');
        $this->assertFalse(property_exists($credit->bank_account, 'uri'));
        $this->assertFalse(property_exists($credit->bank_account, 'id'));
        $this->assertEquals($credit->bank_account->name, 'Homer Jay');
        $this->assertEquals($credit->bank_account->account_number, 'xxx233a');
        $this->assertEquals($credit->bank_account->type, 'checking');
    }

    function testDeleteBankAccount()
    {
        $buyer = self::_createBuyer();
        $buyer->debit(101);  # NOTE: build up escrow balance to credit

        $bank_account = self::_createBankAccount();
        $credit = $bank_account->credit(55, 'something sour');
        $this->assertTrue(property_exists($credit->bank_account, 'uri'));
        $this->assertTrue(property_exists($credit->bank_account, 'id'));
        $bank_account = BankAccount::get($bank_account->id);
        $bank_account->delete();
        $credit = Credit::get($credit->uri);
        $this->assertFalse(property_exists($credit->bank_account, 'uri'));
        $this->assertFalse(property_exists($credit->bank_account, 'id'));
    }

    function testGetBankAccounById()
    {
        $bank_account = self::_createBankAccount();
        $bank_account_2 = BankAccount::get($bank_account->id);
        $this->assertEquals($bank_account_2->id, $bank_account->id);
    }

    /**
     * @expectedException \Balanced\Errors\InsufficientFunds
     */
    function testInsufficientFunds()
    {
        $marketplace = Marketplace::get(self::$marketplace->uri);
        $amount = $marketplace->in_escrow + 100;
        $credit = Credit::bankAccount(
            $amount,
            array(
                'name' => 'Homer Jay',
                'account_number' => '112233a',
                'routing_number' => '121042882',
                'type' => 'checking',
            ),
            'something sour');
    }

    function testCreateCallback() {
        $callback = self::$marketplace->createCallback(
            'http://example.com/php'
        );
        $this->assertEquals($callback->url, 'http://example.com/php');
    }

    /**
     * @expectedException \Balanced\Errors\BankAccountVerificationFailure
     */
    function testBankAccountVerificationFailure() {
        $bank_account = self::_createBankAccount();
        $buyer = self::_createBuyer();
        $buyer->addBankAccount($bank_account);
        $verification = $bank_account->verify();
        $verification->confirm(1, 2);
    }

    /**
     * @expectedException \Balanced\Errors\BankAccountVerificationFailure
     */
    function testBankAccountVerificationDuplicate() {
        $bank_account = self::_createBankAccount();
        $buyer = self::_createBuyer();
        $buyer->addBankAccount($bank_account);
        $bank_account->verify();
        $bank_account->verify();
    }

    function testBankAccountVerificationSuccess() {
        $bank_account = self::_createBankAccount();
        $buyer = self::_createBuyer();
        $buyer->addBankAccount($bank_account);
        $verification = $bank_account->verify();
        $verification->confirm(1, 1);

        //  this will fail if the bank account is not verified
        $debit = $buyer->debit(
            1000,
            'Softie',
            'something i bought',
            array('hi' => 'bye'),
            $bank_account
        );
        $this->assertTrue(strpos($debit->source->uri, 'bank_account') > 0);
    }

    function testEvents() {
        $prev_num_events = Marketplace::mine()->events->total();
        $account = self::_createBuyer();
        $account->debit(123);
        $cur_num_events = Marketplace::mine()->events->total();
        $count = 0;
        while ($cur_num_events == $prev_num_events && $count < 10) {
            printf("waiting for events - %d, %d == %d\n", $count + 1, $cur_num_events, $prev_num_events);
            sleep(2); // 2 seconds
            $cur_num_events = Marketplace::mine()->events->total();
            $count += 1;
        }
        $this->assertTrue($cur_num_events > $prev_num_events);
    }
}
