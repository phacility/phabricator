<?php

namespace Balanced;

use Balanced\Resource;
use \RESTful\URISpec;

/**
 * Represents an account card.
 * 
 * You can create these via Balanced\Marketplace::cards::create or
 * Balanced\Marketplace::createCard. Associate them with a buyer or merchant
 * one creation via Marketplace::createBuyer or
 * Balanced\Marketplace::createMerchant and with an existing buyer or merchant
 * use Balanced\Account::addCard.
 * 
 * <code>
 * $marketplace = \Balanced\Marketplace::mine();
 * 
 * $card = $marketplace->cards->create(array(
 *     'name' => 'name',
 *     'account_number' => '11223344',
 *     'bank_code' => '1313123'
 *     ));
 * 
 * $account = $marketplace
 *     ->accounts
 *     ->query()
 *     ->filter(Account::f->email_address->eq('buyer@example.com'))
 *     ->one();
 * $account->addCard($card->uri);
 * </code>
 */
class Card extends Resource
{
    protected static $_uri_spec = null;

    public static function init()
    {
        self::$_uri_spec = new URISpec('cards', 'id', '/v1');
        self::$_registry->add(get_called_class());
    }
    
    public function debit(
        $amount,
        $appears_on_statement_as = null,
        $description = null,
        $meta = null,
        $source = null)
    {
        if ($this->account == null) {
            throw new \UnexpectedValueException('Card is not associated with an account.');
        }
        return $this->account->debit(
            $amount,
            $appears_on_statement_as,
            $description,
            $meta,
            $this->uri);
    }
}
