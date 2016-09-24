<?php

final class PhabricatorPhurlURLEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'phurl.url';

  public function getEngineName() {
    return pht('Phurl');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorPhurlApplication';
  }

  public function getSummaryHeader() {
    return pht('Configure Phurl Forms');
  }

  public function getSummaryText() {
    return pht('Configure creation and editing forms in Phurl.');
  }

  protected function newEditableObject() {
    return PhabricatorPhurlURL::initializeNewPhurlURL($this->getViewer());
  }

  protected function newObjectQuery() {
    return new PhabricatorPhurlURLQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New URL');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit URL: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create URL');
  }

  protected function getObjectName() {
    return pht('URL');
  }

  protected function getObjectCreateCancelURI($object) {
    return $this->getApplication()->getApplicationURI('/');
  }

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('url/edit/');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      PhabricatorPhurlURLCreateCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('URL name.'))
        ->setConduitTypeDescription(pht('New URL name.'))
        ->setTransactionType(PhabricatorPhurlURLTransaction::TYPE_NAME)
        ->setValue($object->getName()),
      id(new PhabricatorTextEditField())
        ->setKey('url')
        ->setLabel(pht('URL'))
        ->setDescription(pht('The URL to shorten.'))
        ->setConduitTypeDescription(pht('New URL.'))
        ->setValue($object->getLongURL())
        ->setIsRequired(true)
        ->setTransactionType(PhabricatorPhurlURLTransaction::TYPE_URL),
      id(new PhabricatorTextEditField())
        ->setKey('alias')
        ->setLabel(pht('Alias'))
        ->setTransactionType(PhabricatorPhurlURLTransaction::TYPE_ALIAS)
        ->setDescription(pht('The alias to give the URL.'))
        ->setConduitTypeDescription(pht('New alias.'))
        ->setValue($object->getAlias()),
      id(new PhabricatorRemarkupEditField())
        ->setKey('description')
        ->setLabel(pht('Description'))
        ->setDescription(pht('URL long description.'))
        ->setConduitTypeDescription(pht('New URL description.'))
        ->setTransactionType(PhabricatorPhurlURLTransaction::TYPE_DESCRIPTION)
        ->setValue($object->getDescription()),
    );
  }

}
