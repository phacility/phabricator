<?php

final class HarbormasterBuildPlanEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'harbormaster.buildplan';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Harbormaster Build Plans');
  }

  public function getSummaryHeader() {
    return pht('Edit Harbormaster Build Plan Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Harbormaster build plans.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    return HarbormasterBuildPlan::initializeNewBuildPlan($viewer);
  }

  protected function newObjectQuery() {
    return new HarbormasterBuildPlanQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Build Plan');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Build Plan');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Build Plan: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Build Plan');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Build Plan');
  }

  protected function getObjectName() {
    return pht('Build Plan');
  }

  protected function getEditorURI() {
    return '/harbormaster/plan/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/harbormaster/plan/';
  }

  protected function getObjectViewURI($object) {
    $id = $object->getID();
    return "/harbormaster/plan/{$id}/";
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      HarbormasterCreatePlansCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setIsRequired(true)
        ->setTransactionType(HarbormasterBuildPlanTransaction::TYPE_NAME)
        ->setDescription(pht('The build plan name.'))
        ->setConduitDescription(pht('Rename the plan.'))
        ->setConduitTypeDescription(pht('New plan name.'))
        ->setValue($object->getName()),
    );
  }

}
