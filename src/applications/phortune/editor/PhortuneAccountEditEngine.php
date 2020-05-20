<?php

final class PhortuneAccountEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'phortune.account';

  public function getEngineName() {
    return pht('Phortune Accounts');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  public function getSummaryHeader() {
    return pht('Configure Phortune Account Forms');
  }

  public function getSummaryText() {
    return pht('Configure creation and editing forms in Phortune Accounts.');
  }

  public function isEngineConfigurable() {
    return false;
  }

  protected function newEditableObject() {
    return PhortuneAccount::initializeNewAccount($this->getViewer());
  }

  protected function newObjectQuery() {
    return new PhortuneAccountQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Payment Account');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Account: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Account');
  }

  protected function getObjectName() {
    return pht('Account');
  }

  protected function getObjectCreateCancelURI($object) {
    return $this->getApplication()->getApplicationURI('/');
  }

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('edit/');
  }

  protected function getObjectViewURI($object) {
    if ($this->getIsCreate()) {
      return $object->getURI();
    } else {
      return $object->getDetailsURI();
    }
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();

    if ($this->getIsCreate()) {
      $member_phids = array($viewer->getPHID());
    } else {
      $member_phids = $object->getMemberPHIDs();
    }

    $fields = array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Account name.'))
        ->setConduitTypeDescription(pht('New account name.'))
        ->setTransactionType(
          PhortuneAccountNameTransaction::TRANSACTIONTYPE)
        ->setValue($object->getName())
        ->setIsRequired(true),

      id(new PhabricatorUsersEditField())
        ->setKey('managers')
        ->setAliases(array('memberPHIDs', 'managerPHIDs'))
        ->setLabel(pht('Managers'))
        ->setUseEdgeTransactions(true)
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue(
          'edge:type',
          PhortuneAccountHasMemberEdgeType::EDGECONST)
        ->setDescription(pht('Initial account managers.'))
        ->setConduitDescription(pht('Set account managers.'))
        ->setConduitTypeDescription(pht('New list of managers.'))
        ->setInitialValue($object->getMemberPHIDs())
        ->setValue($member_phids),

      id(new PhabricatorTextEditField())
        ->setKey('billingName')
        ->setLabel(pht('Billing Name'))
        ->setDescription(pht('Account name for billing purposes.'))
        ->setConduitTypeDescription(pht('New account billing name.'))
        ->setTransactionType(
          PhortuneAccountBillingNameTransaction::TRANSACTIONTYPE)
        ->setValue($object->getBillingName()),

      id(new PhabricatorTextAreaEditField())
        ->setKey('billingAddress')
        ->setLabel(pht('Billing Address'))
        ->setDescription(pht('Account billing address.'))
        ->setConduitTypeDescription(pht('New account billing address.'))
        ->setTransactionType(
          PhortuneAccountBillingAddressTransaction::TRANSACTIONTYPE)
        ->setValue($object->getBillingAddress()),

    );

    return $fields;

  }

}
