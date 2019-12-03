<?php

final class PhortuneAccountEmailEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'phortune.account.email';

  private $account;

  public function setAccount(PhortuneAccount $account) {
    $this->account = $account;
    return $this;
  }

  public function getAccount() {
    return $this->account;
  }

  public function getEngineName() {
    return pht('Phortune Account Emails');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  public function getSummaryHeader() {
    return pht('Configure Phortune Account Email Forms');
  }

  public function getSummaryText() {
    return pht(
      'Configure creation and editing forms for Phortune Account '.
      'Email Addresses.');
  }

  public function isEngineConfigurable() {
    return false;
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();

    $account = $this->getAccount();
    if (!$account) {
      $account = new PhortuneAccount();
    }

    return PhortuneAccountEmail::initializeNewAddress(
      $account,
      $viewer->getPHID());
  }

  protected function newObjectQuery() {
    return new PhortuneAccountEmailQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Add Email Address');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Account Email: %s', $object->getAddress());
  }

  protected function getObjectEditShortText($object) {
    return pht('%s', $object->getAddress());
  }

  protected function getObjectCreateShortText() {
    return pht('Add Email Address');
  }

  protected function getObjectName() {
    return pht('Account Email');
  }

  protected function getObjectCreateCancelURI($object) {
    return $this->getAccount()->getEmailAddressesURI();
  }

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('address/edit/');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();

    if ($this->getIsCreate()) {
      $address_field = id(new PhabricatorTextEditField())
        ->setTransactionType(
          PhortuneAccountEmailAddressTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true);
    } else {
      $address_field = new PhabricatorStaticEditField();
    }

    $address_field
      ->setKey('address')
      ->setLabel(pht('Email Address'))
      ->setDescription(pht('Email address.'))
      ->setConduitTypeDescription(pht('New email address.'))
      ->setValue($object->getAddress());

    return array(
      $address_field,
    );
  }

}
