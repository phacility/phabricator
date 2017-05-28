<?php

final class PhortuneMerchantEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'phortune.merchant';

  public function getEngineName() {
    return pht('Phortune');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  public function getSummaryHeader() {
    return pht('Configure Phortune Merchant Forms');
  }

  public function getSummaryText() {
    return pht('Configure creation and editing forms for Phortune Merchants.');
  }

  protected function newEditableObject() {
    return PhortuneMerchant::initializeNewMerchant($this->getViewer());
  }

  protected function newObjectQuery() {
    return new PhortuneMerchantQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Merchant');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Merchant: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Merchant');
  }

  protected function getObjectName() {
    return pht('Merchant');
  }

  protected function getObjectCreateCancelURI($object) {
    return $this->getApplication()->getApplicationURI('/');
  }

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('edit/');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  public function isEngineConfigurable() {
    return false;
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      PhortuneMerchantCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();

    if ($this->getIsCreate()) {
      $member_phids = array($viewer->getPHID());
    } else {
      $member_phids = $object->getMemberPHIDs();
    }

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Merchant name.'))
        ->setConduitTypeDescription(pht('New Merchant name.'))
        ->setIsRequired(true)
        ->setTransactionType(
          PhortuneMerchantNameTransaction::TRANSACTIONTYPE)
        ->setValue($object->getName()),

      id(new PhabricatorUsersEditField())
        ->setKey('members')
        ->setAliases(array('memberPHIDs', 'managerPHIDs'))
        ->setLabel(pht('Managers'))
        ->setUseEdgeTransactions(true)
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue(
          'edge:type',
          PhortuneMerchantHasMemberEdgeType::EDGECONST)
        ->setDescription(pht('Initial merchant managers.'))
        ->setConduitDescription(pht('Set merchant managers.'))
        ->setConduitTypeDescription(pht('New list of managers.'))
        ->setInitialValue($object->getMemberPHIDs())
        ->setValue($member_phids),

      id(new PhabricatorRemarkupEditField())
        ->setKey('description')
        ->setLabel(pht('Description'))
        ->setDescription(pht('Merchant description.'))
        ->setConduitTypeDescription(pht('New merchant description.'))
        ->setTransactionType(
          PhortuneMerchantDescriptionTransaction::TRANSACTIONTYPE)
        ->setValue($object->getDescription()),

      id(new PhabricatorRemarkupEditField())
        ->setKey('contactInfo')
        ->setLabel(pht('Contact Info'))
        ->setDescription(pht('Merchant contact information.'))
        ->setConduitTypeDescription(pht('Merchant contact information.'))
        ->setTransactionType(
          PhortuneMerchantContactInfoTransaction::TRANSACTIONTYPE)
        ->setValue($object->getContactInfo()),

      id(new PhabricatorTextEditField())
        ->setKey('invoiceEmail')
        ->setLabel(pht('Invoice From Email'))
        ->setDescription(pht('Email address invoices are sent from.'))
        ->setConduitTypeDescription(
          pht('Email address invoices are sent from.'))
        ->setTransactionType(
          PhortuneMerchantInvoiceEmailTransaction::TRANSACTIONTYPE)
        ->setValue($object->getInvoiceEmail()),

      id(new PhabricatorRemarkupEditField())
        ->setKey('invoiceFooter')
        ->setLabel(pht('Invoice Footer'))
        ->setDescription(pht('Footer on invoice forms.'))
        ->setConduitTypeDescription(pht('Footer on invoice forms.'))
        ->setTransactionType(
          PhortuneMerchantInvoiceFooterTransaction::TRANSACTIONTYPE)
        ->setValue($object->getInvoiceFooter()),

    );
  }

}
