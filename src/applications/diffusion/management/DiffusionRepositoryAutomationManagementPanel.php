<?php

final class DiffusionRepositoryAutomationManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'automation';

  public function getManagementPanelLabel() {
    return pht('Automation');
  }

  public function getManagementPanelOrder() {
    return 800;
  }

  public function getManagementPanelGroupKey() {
    return DiffusionRepositoryManagementBuildsPanelGroup::PANELGROUPKEY;
  }

  public function shouldEnableForRepository(
    PhabricatorRepository $repository) {
    return $repository->isGit();
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'automationBlueprintPHIDs',
    );
  }

  public function getManagementPanelIcon() {
    $repository = $this->getRepository();

    if (!$repository->canPerformAutomation()) {
      return 'fa-truck grey';
    }

    $blueprint_phids = $repository->getAutomationBlueprintPHIDs();
    if (!$blueprint_phids) {
      return 'fa-truck grey';
    }

    $is_authorized = DrydockAuthorizationQuery::isFullyAuthorized(
      $repository->getPHID(),
      $blueprint_phids);
    if (!$is_authorized) {
      return 'fa-exclamation-triangle yellow';
    }

    return 'fa-truck';
  }

  public function buildManagementPanelCurtain() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();
    $action_list = $this->newActionList();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $can_test = $can_edit && $repository->canPerformAutomation();

    $automation_uri = $this->getEditPageURI();
    $test_uri = $repository->getPathURI('edit/testautomation/');

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Automation'))
        ->setHref($automation_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-gamepad')
        ->setName(pht('Test Configuration'))
        ->setWorkflow(true)
        ->setDisabled(!$can_test)
        ->setHref($test_uri));

    return $this->newCurtainView()
      ->setActionList($action_list);
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $blueprint_phids = $repository->getAutomationBlueprintPHIDs();
    if (!$blueprint_phids) {
      $blueprint_view = phutil_tag('em', array(), pht('Not Configured'));
    } else {
      $blueprint_view = id(new DrydockObjectAuthorizationView())
        ->setUser($viewer)
        ->setObjectPHID($repository->getPHID())
        ->setBlueprintPHIDs($blueprint_phids);
    }

    $view->addProperty(pht('Automation'), $blueprint_view);

    return $this->newBox(pht('Automation'), $view);
  }

}
