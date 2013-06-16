<?php

abstract class PhabricatorAuthProvider {

  public function getProviderKey() {
    return $this->getAdapter()->getAdapterKey();
  }

  public static function getAllProviders() {
    static $providers;

    if ($providers === null) {
      $objects = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      $providers = array();
      $from_class_map = array();
      foreach ($objects as $object) {
        $from_class = get_class($object);
        $object_providers = $object->createProviders();
        assert_instances_of($object_providers, 'PhabricatorAuthProvider');
        foreach ($object_providers as $provider) {
          $key = $provider->getProviderKey();
          if (isset($providers[$key])) {
            $first_class = $from_class_map[$key];
            throw new Exception(
              "PhabricatorAuthProviders '{$first_class}' and '{$from_class}' ".
              "both created authentication providers identified by key ".
              "'{$key}'. Provider keys must be unique.");
          }
          $providers[$key] = $provider;
          $from_class_map[$key] = $from_class;
        }
      }
    }

    return $providers;
  }

  public static function getEnabledProviders() {
    $providers = self::getAllProviders();
    foreach ($providers as $key => $provider) {
      if (!$provider->isEnabled()) {
        unset($providers[$key]);
      }
    }
    return $providers;
  }

  public static function getEnabledProviderByKey($provider_key) {
    return idx(self::getEnabledProviders(), $provider_key);
  }

  abstract public function getProviderName();
  abstract public function getAdapater();
  abstract public function isEnabled();
  abstract public function shouldAllowLogin();
  abstract public function shouldAllowRegistration();
  abstract public function shouldAllowAccountLink();
  abstract public function processLoginRequest(
    PhabricatorAuthLoginController $controller);

  public function createProviders() {
    return array($this);
  }

  protected function willSaveAccount(PhabricatorExternalAccount $account) {
    return;
  }

  protected function loadOrCreateAccount($account_id) {
    if (!strlen($account_id)) {
      throw new Exception("loadOrCreateAccount(...): empty account ID!");
    }

    $adapter = $this->getAdapter();
    $account = id(new PhabricatorExternalAccount())->loadOneWhere(
      'accountType = %s AND accountDomain = %s AND accountID = %s',
      $adapter->getProviderType(),
      $adapter->getProviderDomain(),
      $account_id);
    if (!$account) {
      $account = id(new PhabricatorExternalAccount())
        ->setAccountType($adapter->getProviderType())
        ->setAccountDomain($adapter->getProviderDomain())
        ->setAccountID($account_id);
    }

    $account->setUsername($adapter->getAccountName());
    $account->setRealName($adapter->getAccountRealName());
    $account->setEmail($adapter->getAccountEmail());
    $account->setAccountURI($adapter->getAccountURI());

    try {
      $name = PhabricatorSlug::normalize($this->getProviderName());
      $name = $name.'-profile.jpg';

      // TODO: If the image has not changed, we do not need to make a new
      // file entry for it, but there's no convenient way to do this with
      // PhabricatorFile right now. The storage will get shared, so the impact
      // here is negligible.

      $image_uri = $account->getAccountImageURI();
      $image_file = PhabricatorFile::newFromFileDownload(
        $image_uri,
        array(
          'name' => $name,
        ));

      $account->setProfileImagePHID($image_file->getPHID());
    } catch (Exception $ex) {
      $account->setProfileImagePHID(null);
    }

    $this->willSaveAccount($account);

    $account->save();

    return $account;
  }



}
