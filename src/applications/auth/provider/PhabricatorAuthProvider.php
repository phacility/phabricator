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

  public static function getAllEnabledProviders() {
    $providers = self::getAllProviders();
    foreach ($providers as $key => $provider) {
      if (!$provider->isEnabled()) {
        unset($providers[$key]);
      }
    }
    return $providers;
  }

  public static function getEnabledProviderByKey($provider_key) {
    return idx(self::getAllEnabledProviders(), $provider_key);
  }

  abstract public function getProviderName();
  abstract public function getAdapter();

  public function isEnabled() {
    // TODO: Remove once we switch to the new auth stuff.
    return false;
  }

  abstract public function shouldAllowLogin();
  abstract public function shouldAllowRegistration();
  abstract public function shouldAllowAccountLink();
  abstract public function shouldAllowAccountUnlink();

  abstract public function buildLoginForm(
    PhabricatorAuthStartController $controller);

  abstract public function processLoginRequest(
    PhabricatorAuthLoginController $controller);

  public function createProviders() {
    return array($this);
  }

  protected function willSaveAccount(PhabricatorExternalAccount $account) {
    return;
  }

  public function willRegisterAccount(PhabricatorExternalAccount $account) {
    return;
  }

  protected function loadOrCreateAccount($account_id) {
    if (!strlen($account_id)) {
      throw new Exception(
        "loadOrCreateAccount(...): empty account ID!");
    }

    $adapter = $this->getAdapter();
    $adapter_class = get_class($adapter);

    if (!strlen($adapter->getAdapterType())) {
      throw new Exception(
        "AuthAdapter (of class '{$adapter_class}') has an invalid ".
        "implementation: no adapter type.");
    }

    if (!strlen($adapter->getAdapterDomain())) {
      throw new Exception(
        "AuthAdapter (of class '{$adapter_class}') has an invalid ".
        "implementation: no adapter domain.");
    }

    $account = id(new PhabricatorExternalAccount())->loadOneWhere(
      'accountType = %s AND accountDomain = %s AND accountID = %s',
      $adapter->getAdapterType(),
      $adapter->getAdapterDomain(),
      $account_id);
    if (!$account) {
      $account = id(new PhabricatorExternalAccount())
        ->setAccountType($adapter->getAdapterType())
        ->setAccountDomain($adapter->getAdapterDomain())
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

  protected function getLoginURI() {
    $app = PhabricatorApplication::getByClass('PhabricatorApplicationAuth');
    $uri = $app->getApplicationURI('/login/'.$this->getProviderKey().'/');
    return PhabricatorEnv::getURI($uri);
  }

  public function isDefaultRegistrationProvider() {
    return false;
  }

  public function shouldRequireRegistrationPassword() {
    return false;
  }

  public function getDefaultExternalAccount() {
    throw new Exception("Not implemented!");
  }

}
