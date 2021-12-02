<?php

final class HarbormasterBuildEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'harbormaster.build';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Harbormaster Builds');
  }

  public function getSummaryHeader() {
    return pht('Edit Harbormaster Build Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Harbormaster builds.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return HarbormasterBuild::initializeNewBuild($viewer);
  }

  protected function newObjectQuery() {
    return new HarbormasterBuildQuery();
  }

  protected function newEditableObjectForDocumentation() {
    $object = new DifferentialRevision();

    $buildable = id(new HarbormasterBuildable())
      ->attachBuildableObject($object);

    return $this->newEditableObject()
      ->attachBuildable($buildable);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Build');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Build');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Build: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Build');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Build');
  }

  protected function getObjectName() {
    return pht('Build');
  }

  protected function getEditorURI() {
    return '/harbormaster/build/edit/';
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
