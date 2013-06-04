<?php

namespace Balanced;

use Balanced\Resource;
use \RESTful\URISpec;

/**
 * Represents an account bank account.
 * 
 * You can create these via Balanced\Marketplace::bank_accounts::create or
 * Balanced\Marketplace::createBankAccount. Associate them with a buyer or
 * merchant one creation via Balanced\Marketplace::createBuyer or
 * Balanced\Marketplace::createMerchant and with an existing buyer or merchant
 * use Balanced\Account::addBankAccount.
 * 
 * <code>
 * $marketplace = \Balanced\Marketplace::mine();
 * 
 * $bank_account = $marketplace->bank_accounts->create(array(
 *     'name' => 'name',
 *     'account_number' => '11223344',
 *     'bank_code' => '1313123',
 *     ));
 *     
 * $account = $marketplace
 *     ->accounts
 *     ->query()
 *     ->filter(Account::f->email_address->eq('merchant@example.com'))
 *     ->one();
 * $account->addBankAccount($bank_account->uri);
 * </code>
 */
class BankAccount extends Resource
{
    protected static $_uri_spec = null;
    
    public static function init()
    {
        self::$_uri_spec = new URISpec('bank_accounts', 'id', '/v1');
        self::$_registry->add(get_called_class());
    }
    
    /**
     * Credit a bank account.
     *
     * @param int amount Amount to credit in USD pennies.
     * @param string description Optional description of the credit.
     * @param string appears_on_statement_as Optional description of the credit as it will appears on the customer's billing statement.
     *
     * @return \Balanced\Credit
     *
     * <code>
     * $bank_account = new \Balanced\BankAccount(array(
     *     'account_number' => '12341234',
     *     'name' => 'Fit Finlay',
     *     'bank_code' => '325182797',
     *     'type' => 'checking',
     *     ));
     *     
     * $credit = $bank_account->credit(123, 'something descriptive');
     * </code>
     */
    public function credit(
            $amount,
            $description = null,
            $meta = null,
            $appears_on_statement_as = null)
    {
        if (!property_exists($this, 'account') || $this->account == null) {
            $credit = $this->credits->create(array(
                'amount' => $amount,
                'description' => $description,
            ));
        } else {
            $credit = $this->account->credit(
                $amount,
                $description,
                $meta,
                $this->uri,
                $appears_on_statement_as
            );
        }
        return $credit;
    }

    public function verify()
    {
        $response = self::getClient()->post(
            $this->verifications_uri, null
        );
        $verification = new BankAccountVerification();
        $verification->_objectify($response->body);
        return $verification;
    }
}

/**
 * Represents an verification for a bank account which is a pre-requisite if
 * you want to create debits using the associated bank account. The side-effect
 * of creating a verification is that 2 random amounts will be deposited into
 * the account which must then be confirmed via the confirm method to ensure
 * that you have access to the bank account in question.
 *
 * You can create these via Balanced\Marketplace::bank_accounts::verify.
 *
 * <code>
 * $marketplace = \Balanced\Marketplace::mine();
 *
 * $bank_account = $marketplace->bank_accounts->create(array(
 *     'name' => 'name',
 *     'account_number' => '11223344',
 *     'bank_code' => '1313123',
 *     ));
 *
 * $verification = $bank_account->verify();
 * </code>
 */
class BankAccountVerification extends Resource {

    public function confirm($amount1, $amount2) {
        $this->amount_1 = $amount1;
        $this->amount_2 = $amount2;
        $this->save();
        return $this;
    }
}
