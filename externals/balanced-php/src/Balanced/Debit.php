<?php

namespace Balanced;

use Balanced\Resource;
use \RESTful\URISpec;

/**
 * Represents an account debit transaction.
 * 
 * You create these using Balanced\Account::debit.
 * 
 * <code>
 * $marketplace = \Balanced\Marketplace::mine();
 * 
 * $account = $marketplace
 *     ->accounts
 *     ->query()
 *     ->filter(Account::f->email_address->eq('buyer@example.com'))
 *     ->one();
 * 
 * $debit = $account->debit(
 *     100,
 *     'how it appears on the statement',
 *     'a description',
 *     array(
 *         'my_id': '443322'
 *         )
 *     );
 * </code>
 */
class Debit extends Resource
{
    protected static $_uri_spec = null;

    public static function init()
    {
        self::$_uri_spec = new URISpec('debits', 'id');
        self::$_registry->add(get_called_class());
    }
    
    /**
     * Create a refund for this debit. You can create multiple refunds for a
     * debit but the total amount of the refunds must be less than the debit
     * amount.  
     * 
     * @param int amount Optional amount of the refund in USD pennies. If unspecified then the full debit amount is used.    
     * @param string description Optional description of the refund.
     * @param array[string]string meta Optional metadata to associate with the refund.
     * 
     * @return \Balanced\Refund
     */
    public function refund(
        $amount = null,
        $description = null,
        $meta = null)
    {
        return $this->refunds->create(array(
            'amount' => $amount,
            'description' => $description,
            'meta' => $meta
            ));
    }
}
