<?php

namespace Balanced;

use Balanced\Resource;
use \RESTful\URISpec;

/**
 * Represents an account credit transaction.
 * 
 * You create these using Balanced\Account::credit.
 * 
 * <code>
 * $marketplace = \Balanced\Marketplace::mine();
 * 
 * $account = $marketplace
 *     ->accounts
 *     ->query()
 *     ->filter(Account::f->email_address->eq('merchant@example.com'))
 *     ->one();
 * 
 * $credit = $account->credit(
 *     100,
 *     'how it '
 *     array(
 *         'my_id': '112233'
 *         )
 *     );
 * </code>
 */
class Credit extends Resource
{
    protected static $_uri_spec = null;

    public static function init()
    {
        self::$_uri_spec = new URISpec('credits', 'id', '/v1');
        self::$_registry->add(get_called_class());
    }
    
    /**
     * Credit an unstored bank account.
     *
     * @param int amount Amount to credit in USD pennies.
     * @param string description Optional description of the credit.
     * @param mixed bank_account Associative array describing a bank account to credit. The bank account will *not* be stored.
     *
     * @return \Balanced\Credit
     *  
     * <code>
     * $credit = \Balanced\Credit::bankAccount(
     *     123,
     *     array(
     *     'account_number' => '12341234',
     *     'name' => 'Fit Finlay',
     *     'bank_code' => '325182797',
     *     'type' => 'checking',
     *     ),
     *     'something descriptive');
     * </code>
     */
    public static function bankAccount(
        $amount,
        $bank_account,
        $description = null)
    {
        $credit = new Credit(array(
           'amount' => $amount,
           'bank_account' => $bank_account,
           'description' => $description
        ));
        $credit->save();
        return $credit;
    }
}
