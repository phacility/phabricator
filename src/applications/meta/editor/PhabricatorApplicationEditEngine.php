<?php

final class PhabricatorApplicationEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'application.application';

  public function getEngineApplicationClass() {
    return 'PhabricatorApplicationsApplication';
  }

  public function getEngineName() {
    return pht('Applications');
  }

  public function getSummaryHeader() {
    return pht('Configure Application Forms');
  }

  public function getSummaryText() {
    return pht('Configure creation and editing forms in Applications.');
  }

  public function isEngineConfigurable() {
    return false;
  }

  protected function newEditableObject() {
    throw new PhutilMethodNotImplementedException();
  }

  protected function newObjectQuery() {
    return new PhabricatorApplicationQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Application');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Application: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Application');
  }

  protected function getObjectName() {
    return pht('Application');
  }

  protected function getObjectViewURI($object) {
    return $object->getViewURI();
  }

  protected function buildCustomEditFields($object) {
    return array();
  }

}
