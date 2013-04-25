<?php

namespace Balanced;

use Balanced\Resource;
use \RESTful\URISpec;

/**
 * Represents a refund of an account debit transaction.
 * 
 * You create these via Balanced\Debit::refund.
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
 * 
 * $debit->refund(
 *     99,
 *     'some description',
 *     array(
 *         'my_id': '123123'
 *         )
 *     );
 * </code>
 */
class Refund extends Resource
{
    protected static $_uri_spec = null;

    public static function init()
    {
        self::$_uri_spec = new URISpec('refunds', 'id');
        self::$_registry->add(get_called_class());
    }
}

