<?php

final class PhabricatorUserOAuthInfo {

  private $account;
  private $token;

  public function getID() {
    return $this->account->getID();
  }

  public function setToken($token) {
    $this->token = $token;
    return $this;
  }

  public function getToken() {
    return $this->token;
  }

  public function __construct(PhabricatorExternalAccount $account) {
    $this->account = $account;
  }

  public function setAccountURI($value) {
    $this->account->setAccountURI($value);
    return $this;
  }

  public function getAccountURI() {
    return $this->account->getAccountURI();
  }

  public function setAccountName($account_name) {
    $this->account->setUsername($account_name);
    return $this;
  }

  public function getAccountName() {
    return $this->account->getUsername();
  }

  public function setUserID($user_id) {
    $user = id(new PhabricatorUser())->loadOneWhere('id = %d', $user_id);
    if (!$user) {
      throw new Exception("No such user with given ID!");
    }
    $this->account->setUserPHID($user->getPHID());
    return $this;
  }

  public function getUserID() {
    $phid = $this->account->getUserPHID();
    if (!$phid) {
      return null;
    }

    $user = id(new PhabricatorUser())->loadOneWhere('phid = %s', $phid);
    if (!$user) {
      throw new Exception("No such user with given PHID!");
    }

    return $user->getID();
  }

  public function setOAuthUID($oauth_uid) {
    $this->account->setAccountID($oauth_uid);
    return $this;
  }

  public function getOAuthUID() {
    return $this->account->getAccountID();
  }

  public function setOAuthProvider($oauth_provider) {
    $domain = self::getDomainForProvider($oauth_provider);
    $this->account->setAccountType($oauth_provider);
    $this->account->setAccountDomain($domain);

    return $this;
  }

  public function getOAuthProvider() {
    return $this->account->getAccountType();
  }

  public static function loadOneByUserAndProviderKey(
    PhabricatorUser $user,
    $provider_key) {

    $account = id(new PhabricatorExternalAccount())->loadOneWhere(
      'userPHID = %s AND accountType = %s AND accountDomain = %s',
      $user->getPHID(),
      $provider_key,
      self::getDomainForProvider($provider_key));

    if (!$account) {
      return null;
    }

    return new PhabricatorUserOAuthInfo($account);
  }

  public static function loadAllOAuthProvidersByUser(
    PhabricatorUser $user) {

    $accounts = id(new PhabricatorExternalAccount())->loadAllWhere(
      'userPHID = %s',
      $user->getPHID());

    $results = array();
    foreach ($accounts as $account) {
      $results[] = new PhabricatorUserOAuthInfo($account);
    }

    return $results;
  }

  public static function loadOneByProviderKeyAndAccountID(
    $provider_key,
    $account_id) {

    $account = id(new PhabricatorExternalAccount())->loadOneWhere(
      'accountType = %s AND accountDomain = %s AND accountID = %s',
      $provider_key,
      self::getDomainForProvider($provider_key),
      $account_id);

    if (!$account) {
      return null;
    }

    return new PhabricatorUserOAuthInfo($account);
  }

  public function save() {
    $this->account->save();
    return $this;
  }

  private static function getDomainForProvider($provider_key) {
    $domain_map = array(
      'disqus'      => 'disqus.com',
      'facebook'    => 'facebook.com',
      'github'      => 'github.com',
      'google'      => 'google.com',
    );

    try {
      $phabricator_oauth_uri = new PhutilURI(
        PhabricatorEnv::getEnvConfig('phabricator.oauth-uri'));
      $domain_map['phabricator'] = $phabricator_oauth_uri->getDomain();
    } catch (Exception $ex) {
      // Ignore.
    }

    return idx($domain_map, $provider_key);
  }

}
