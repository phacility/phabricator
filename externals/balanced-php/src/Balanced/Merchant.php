<?php

namespace Balanced;

use Balanced\Resource;
use \RESTful\URISpec;

/**
 * Represents a merchant identity.
 * 
 * These are optionally created and associated with an account via
 * \Balanced\Marketplace::createMerchant which establishes a merchant account
 * on a marketplace.
 * 
 * In some cases a merchant may need to be redirected to create a identity (e.g. the
 * information provided cannot be verified, more information is needed, etc). That
 * redirected signup results in a merchant_uri which is then associated with an
 * account on the marketplace via \Balanced\Marketplace::createMerchant.
 * 
 * @see \Balanced\Marketplace
 */
class Merchant extends Resource
{
    protected static $_uri_spec = null;

    public static function init()
    {
        self::$_uri_spec = new URISpec('merchants', 'id', '/v1');
        self::$_registry->add(get_called_class());
    }

    /**
     * Return the merchant identity associated with the current
     * Balanced\Settings::$api_key. If you are not authenticated (i.e. 
     * ) then Balanced\Exceptions\NoResult
     * will be thrown.
     * 
     * <code>
     * $merchant = \Balanced\Merchant::me();
     * $owner_account = \Balanced\Marketplace::mine()->owner_account;
     * assert($merchant->id == $owner_account->merchant->id);
     * </code>
     *
     * @throws \RESTful\Exceptions\NoResultFound
     * @return \Balanced\Merchant
     */
    public static function me()
    {
        return self::query()->one();
    }
}
