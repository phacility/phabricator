<?php

final class DiffusionCommitEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'diffusion.commit';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Commits');
  }

  public function getSummaryHeader() {
    return pht('Edit Commits');
  }

  public function getSummaryText() {
    return pht('Edit commits.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function newEditableObject() {
    throw new PhutilMethodNotImplementedException();
  }

  protected function newObjectQuery() {
    return new DiffusionCommitQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Commit');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Commit');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Commit: %s', $object->getDisplayName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getDisplayName();
  }

  protected function getObjectName() {
    return pht('Commit');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getCreateNewObjectPolicy() {
    return PhabricatorPolicies::POLICY_NOONE;
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();

    return array();
  }

}
