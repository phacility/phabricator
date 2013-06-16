<?php

abstract class PhabricatorAuthProvider {

  private $adapter;

  public function setAdapater(PhutilAuthAdapter $adapter) {
    $this->adapter = $adapter;
    return $this;
  }

  public function getAdapater() {
    if ($this->adapter === null) {
      throw new Exception("Call setAdapter() before getAdapter()!");
    }
    return $this->adapter;
  }

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
  abstract public function isEnabled();
  abstract public function shouldAllowLogin();
  abstract public function shouldAllowRegistration();
  abstract public function shouldAllowAccountLink();
  abstract public function processLoginRequest(
    PhabricatorAuthLoginController $controller);

  public function createProviders() {
    return array($this);
  }

}
