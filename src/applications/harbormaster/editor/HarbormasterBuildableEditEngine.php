<?php

final class HarbormasterBuildableEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'harbormaster.buildable';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Harbormaster Buildables');
  }

  public function getSummaryHeader() {
    return pht('Edit Harbormaster Buildable Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Harbormaster buildables.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return HarbormasterBuildable::initializeNewBuildable($viewer);
  }

  protected function newObjectQuery() {
    return new HarbormasterBuildableQuery();
  }

  protected function newEditableObjectForDocumentation() {
    $object = new DifferentialRevision();

    return $this->newEditableObject()
      ->attachBuildableObject($object);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Buildable');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Buildable');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Buildable: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Buildable');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Buildable');
  }

  protected function getObjectName() {
    return pht('Buildable');
  }

  protected function getEditorURI() {
    return '/harbormaster/buildable/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/harbormaster/';
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function buildCustomEditFields($object) {
    return array();
  }

}
