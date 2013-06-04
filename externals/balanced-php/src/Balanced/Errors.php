<?php

namespace Balanced\Errors;

use RESTful\Exceptions\HTTPError;

class Error extends HTTPError
{
    public static $codes = array();
    
    public static function init()
    {
        foreach (get_declared_classes() as $class) {
            $parent_class = get_parent_class($class);
            if ($parent_class != 'Balanced\Errors\Error')
                continue;
            foreach ($class::$codes as $type)
                self::$codes[$type] = $class;
        }
    }
}

class DuplicateAccountEmailAddress extends Error
{
    public static $codes = array('duplicate-email-address');
}

class InvalidAmount extends Error
{
    public static $codes = array('invalid-amount');
}

class InvalidRoutingNumber extends Error
{
    public static $codes = array('invalid-routing-number');
}

class InvalidBankAccountNumber extends Error
{
    public static $codes = array('invalid-bank-account-number');
}

class Declined extends Error
{
    public static $codes = array('funding-destination-declined', 'authorization-failed');
}

class CannotAssociateMerchantWithAccount extends Error
{
    public static $codes = array('cannot-associate-merchant-with-account');
}

class AccountIsAlreadyAMerchant extends Error
{
    public static $codes = array('account-already-merchant');
}

class NoFundingSource extends Error
{
    public static $codes = array('no-funding-source');
}

class NoFundingDestination extends Error
{
    public static $codes = array('no-funding-destination');
}

class CardAlreadyAssociated extends Error
{
    public static $codes = array('card-already-funding-src');
}

class CannotAssociateCard extends Error
{
    public static $codes = array('cannot-associate-card');
}

class BankAccountAlreadyAssociated extends Error
{
    public static $codes = array('bank-account-already-associated');
}

class AddressVerificationFailed extends Error
{
    public static $codes = array('address-verification-failed');
}

class HoldExpired extends Error
{
    public static $codes = array('authorization-expired');
}

class MarketplaceAlreadyCreated extends Error
{
    public static $codes = array('marketplace-already-created');
}

class IdentityVerificationFailed extends Error
{
    public static $codes = array('identity-verification-error', 'business-principal-kyc', 'business-kyc', 'person-kyc');
}

class InsufficientFunds extends Error
{
    public static $codes = array('insufficient-funds');
}

class CannotHold extends Error
{
    public static $codes = array('funding-source-not-hold');
}

class CannotCredit extends Error
{
    public static $codes = array('funding-destination-not-creditable');
}

class CannotDebit extends Error
{
    public static $codes = array('funding-source-not-debitable');
}

class CannotRefund extends Error
{
    public static $codes = array('funding-source-not-refundable');
}

class BankAccountVerificationFailure extends Error
{
    public static $codes = array(
        'bank-account-authentication-not-pending',
        'bank-account-authentication-failed',
        'bank-account-authentication-already-exists'
    );
}
