<?php

namespace Balanced;

use Balanced\Resource;
use \RESTful\URISpec;

/**
 * Represents pending debit of funds for an account. The funds for that debit
 * are held by the processor. You can later capture the hold, which results in
 * debit, or void it, which releases the held funds.
 * 
 * Note that a hold can expire so you should always check
 * Balanced\Hold::expires_at.
 * 
 * You create these using \Balanced\Account::hold.
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
 * $hold = $account->hold(
 *     100,
 *     'a description',
 *     null,
 *     array(
 *         'my_id': '1293712837'
 *         )
 *     );
 * 
 * $debit = $hold->capture(); 
 * </code>
 */
class Hold extends Resource
{
    protected static $_uri_spec = null;
    
    public static function init()
    {
        self::$_uri_spec = new URISpec('holds', 'id');
        self::$_registry->add(get_called_class());
    }
    
    /**
     ** Voids a pending hold. This releases the held funds. Once voided a hold
     * is not longer pending can cannot be re-captured or re-voided.
     * 
     * @return \Balanced\Hold
     */
    public function void()
    {
        $this->is_void = true;
        return $this->save();
    }
    
    /**
     * Captures a pending hold. This results in a debit. Once captured a hold
     * is not longer pending can cannot be re-captured or re-voided.
     * 
     * @param int amount Optional Portion of the pending hold to capture. If not specified the full amount associated with the hold is captured.
     * 
     * @return \Balanced\Debit
     */
    public function capture($amount = null)
    {
        $this->debit = $this->account->debits->create(array(
            'hold_uri' => $this->uri,
            'amount' => $amount,
            ));
        return $this->debit;
    }
}
