<?php

final class PhabricatorAuthContactNumberEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'auth.contact';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Contact Numbers');
  }

  public function getSummaryHeader() {
    return pht('Edit Contact Numbers');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit contact numbers.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return PhabricatorAuthContactNumber::initializeNewContactNumber($viewer);
  }

  protected function newObjectQuery() {
    return new PhabricatorAuthContactNumberQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Contact Number');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Contact Number');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Contact Number');
  }

  protected function getObjectEditShortText($object) {
    return $object->getObjectName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Contact Number');
  }

  protected function getObjectName() {
    return pht('Contact Number');
  }

  protected function getEditorURI() {
    return '/auth/contact/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/settings/panel/contact/';
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('contactNumber')
        ->setTransactionType(
          PhabricatorAuthContactNumberNumberTransaction::TRANSACTIONTYPE)
        ->setLabel(pht('Contact Number'))
        ->setDescription(pht('The contact number.'))
        ->setValue($object->getContactNumber())
        ->setIsRequired(true),
    );
  }

}
