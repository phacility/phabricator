<?php

final class PhortuneAccountEmail
  extends PhortuneDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface {

  protected $accountPHID;
  protected $authorPHID;
  protected $address;
  protected $status;
  protected $addressKey;
  protected $accessKey;

  private $account = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'address' => 'sort128',
        'status' => 'text32',
        'addressKey' => 'text32',
        'accessKey' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_account' => array(
          'columns' => array('accountPHID', 'address'),
          'unique' => true,
        ),
        'key_address' => array(
          'columns' => array('addressKey'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhortuneAccountEmailPHIDType::TYPECONST;
  }

  public static function initializeNewAddress(
    PhortuneAccount $account,
    $author_phid) {

    $address_key = Filesystem::readRandomCharacters(16);
    $access_key = Filesystem::readRandomCharacters(16);
    $default_status = PhortuneAccountEmailStatus::getDefaultStatusConstant();

    return id(new self())
      ->setAuthorPHID($author_phid)
      ->setAccountPHID($account->getPHID())
      ->setStatus($default_status)
      ->attachAccount($account)
      ->setAddressKey($address_key)
      ->setAccessKey($access_key);
  }

  public function attachAccount(PhortuneAccount $account) {
    $this->account = $account;
    return $this;
  }

  public function getAccount() {
    return $this->assertAttached($this->account);
  }

  public function getObjectName() {
    return pht('Account Email %d', $this->getID());
  }

  public function getURI() {
    return urisprintf(
      '/phortune/account/%d/addresses/%d/',
      $this->getAccount()->getID(),
      $this->getID());
  }

  public function getExternalURI() {
    return urisprintf(
      '/phortune/external/%s/%s/',
      $this->getAddressKey(),
      $this->getAccessKey());
  }

  public function getUnsubscribeURI() {
    return urisprintf(
      '/phortune/external/%s/%s/unsubscribe/',
      $this->getAddressKey(),
      $this->getAccessKey());
  }

  public function getExternalOrderURI(PhortuneCart $cart) {
    return urisprintf(
      '/phortune/external/%s/%s/order/%d/',
      $this->getAddressKey(),
      $this->getAccessKey(),
      $cart->getID());
  }

  public function getExternalOrderPrintURI(PhortuneCart $cart) {
    return urisprintf(
      '/phortune/external/%s/%s/order/%d/print/',
      $this->getAddressKey(),
      $this->getAccessKey(),
      $cart->getID());
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    return array(
      array($this->getAccount(), $capability),
    );
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhortuneAccountEmailEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhortuneAccountEmailTransaction();
  }

}
