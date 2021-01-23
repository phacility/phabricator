<?php

final class HarbormasterBuildStepEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'harbormaster.buildstep';

  private $buildPlan;

  public function setBuildPlan(HarbormasterBuildPlan $build_plan) {
    $this->buildPlan = $build_plan;
    return $this;
  }

  public function getBuildPlan() {
    if ($this->buildPlan === null) {
      throw new PhutilInvalidStateException('setBuildPlan');
    }

    return $this->buildPlan;
  }

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Harbormaster Build Steps');
  }

  public function getSummaryHeader() {
    return pht('Edit Harbormaster Build Step Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit Harbormaster build steps.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();


    $plan = HarbormasterBuildPlan::initializeNewBuildPlan($viewer);
    $this->setBuildPlan($plan);

    $plan = $this->getBuildPlan();

    $step = HarbormasterBuildStep::initializeNewStep($viewer);

    $step->setBuildPlanPHID($plan->getPHID());
    $step->attachBuildPlan($plan);

    return $step;
  }

  protected function newObjectQuery() {
    return new HarbormasterBuildStepQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Build Step');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Build Step');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Build Step: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Build Step');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Build Step');
  }

  protected function getObjectName() {
    return pht('Build Step');
  }

  protected function getEditorURI() {
    return '/harbormaster/step/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/harbormaster/step/';
  }

  protected function getObjectViewURI($object) {
    $id = $object->getID();
    return "/harbormaster/step/{$id}/";
  }

  protected function buildCustomEditFields($object) {
    $fields = array();

    return $fields;
  }

}
