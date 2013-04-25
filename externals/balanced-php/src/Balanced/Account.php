<?php

namespace Balanced;

use Balanced\Resource;
use \RESTful\URISpec;

/**
 * Represent a buyer or merchant account on a marketplace.
 * 
 * You create these using Balanced\Marketplace->createBuyer or 
 * Balanced\Marketplace->createMerchant.
 * 
 * <code>
 * $marketplace = \Balanced\Marketplace::mine();
 * 
 * $card = $marketplace->cards->create(array(
 *     'street_address' => $street_address,
 *     'city' => 'Jollywood',
 *     'region' => 'CA',
 *     'postal_code' => '90210',
 *     'name' => 'Captain Chunk',
 *     'card_number' => '4111111111111111',
 *     'expiration_month' => 7,
 *     'expiration_year' => 2015
 *     ));
 *     
 * $buyer = $marketplace->createBuyer(
 *     'buyer@example.com',
 *     $card->uri,
 *     array(
 *         'my_id' => '1212121',
 *         )
 *     );
 * </code>
 * 
 * @see Balanced\Marketplace->createBuyer
 * @see Balanced\Marketplace->createMerchant
 */
class Account extends Resource
{
    protected static $_uri_spec = null;

    public static function init()
    {
        self::$_uri_spec = new URISpec('accounts', 'id');
        self::$_registry->add(get_called_class());
    }
    
    /**
     * Credit the account.
     * 
     * @param int amount Amount to credit the account in USD pennies.
     * @param string description Optional description of the credit.
     * @param array[string]string meta Optional metadata to associate with the credit.
     * @param mixed destination Optional URI of a funding destination (i.e. \Balanced\BankAccount) associated with this account to credit. If not specified the funding destination most recently added to the account is used.
     * @param string appears_on_statement_as Optional description of the credit as it will appears on the customer's billing statement.
     * 
     * @return \Balanced\Credit
     */
    public function credit(
        $amount,
        $description = null,
        $meta = null,
        $destination = null,
        $appears_on_statement_as = null)
    {
        if ($destination == null)
            $destination_uri = null;
        else
            $destination_uri = is_string($destination) ? $destination : $destination->uri;
        return $this->credits->create(array(
            'amount' => $amount,
            'description' => $description,
            'meta' => $meta,
            'destination_uri' => $destination_uri,
            'appears_on_statement_as' => $appears_on_statement_as
            ));
    }
    
    /**
     * Debit the account.
     * 
     * @param int amount Amount to debit the account in USD pennies.   
     * @param string appears_on_statement_as Optional description of the debit as it will appears on the customer's billing statement.
     * @param string description Optional description of the debit.
     * @param array[string]string meta Optional metadata to associate with the debit.
     * @param mixed Optional funding source (i.e. \Balanced\Card) or URI of a funding source associated with this account to debit. If not specified the funding source most recently added to the account is used.
     * 
     * @return \Balanced\Debit
     */
    public function debit(
        $amount,
        $appears_on_statement_as = null,
        $description = null,
        $meta = null,
        $source = null,
        $on_behalf_of = null)
    {
        if ($source == null) {
            $source_uri = null;
        } else if (is_string($source)) {
            $source_uri = $source;
        } else {
            $source_uri = $source->uri;
        }

        if ($on_behalf_of == null) {
            $on_behalf_of_uri = null;
        } else if (is_string($on_behalf_of)) {
            $on_behalf_of_uri = $on_behalf_of;
        } else {
            $on_behalf_of_uri = $on_behalf_of->uri;
        }

        if (isset($this->uri) && $on_behalf_of_uri == $this->uri)
            throw new \InvalidArgumentException(
                'The on_behalf_of parameter MAY NOT be the same account as the account you are debiting!'
            );

        return $this->debits->create(array(
            'amount' => $amount,
            'description' => $description,
            'meta' => $meta,
            'source_uri' => $source_uri,
            'on_behalf_of_uri' => $on_behalf_of_uri,
            'appears_on_statement_as' => $appears_on_statement_as
            ));
    }
    
    /**
     * Create a hold (i.e. a guaranteed pending debit) for account funds. You
     * can later capture or void. A hold is associated with a account funding
     * source (i.e. \Balanced\Card). If you don't specify the source then the
     * current primary funding source for the account is used. 
     * 
     * @param int amount Amount of the hold in USD pennies.
     * @param string Optional description Description of the hold.
     * @param string Optional URI referencing the card to use for the hold.
     * @param array[string]string meta Optional metadata to associate with the hold.
     * 
     * @return \Balanced\Hold
     */
    public function hold(
        $amount,
        $description = null,
        $source_uri = null,
        $meta = null)
    {
        return $this->holds->create(array(
            'amount' => $amount,
            'description' => $description,
            'source_uri' => $source_uri,
            'meta' => $meta
            ));
    }
    
    /**
     * Creates or associates a created card with the account. The default
     * funding source for the account will be this card.
     * 
     * @see \Balanced\Marketplace->createCard
     * 
     * @param mixed card \Balanced\Card or URI referencing a card to associate with the account. Alternatively it can be an associative array describing a card to create and associate with the account.
     * 
     * @return \Balanced\Account
     */
    public function addCard($card)
    {
        if (is_string($card))
            $this->card_uri = $card;
        else if (is_array($card))
            $this->card = $card;
        else
            $this->card_uri = $card->uri;
        return $this->save();
    }
    
    /**
     * Creates or associates a created bank account with the account. The
     * new default funding destination for the account will be this bank account.
     * 
     * @see \Balanced\Marketplace->createBankAccount
     * 
     * @param mixed bank_account \Balanced\BankAccount or URI for a bank account to associate with the account. Alternatively it can be an associative array describing a bank account to create and associate with the account.
     * 
     * @return \Balanced\Account
     */
    public function addBankAccount($bank_account)
    {
        if (is_string($bank_account))
            $this->bank_account_uri = $bank_account;
        else if (is_array($bank_account))
            $this->bank_account = $bank_account;
        else
            $this->bank_account_uri = $bank_account->uri;
        return $this->save();
    }
    
    /**
     * Promotes a role-less or buyer account to a merchant.
     * 
     * @see Balanced\Marketplace::createMerchant
     *
     * @param mixed merchant Associative array describing the merchants identity or a URI referencing a created merchant.
     *       
     * @return \Balanced\Account
     */
    public function promoteToMerchant($merchant)
    {
        if (is_string($merchant))
            $this->merchant_uri = $merchant;
        else
            $this->merchant = $merchant;
        return $this->save();
    }
}
