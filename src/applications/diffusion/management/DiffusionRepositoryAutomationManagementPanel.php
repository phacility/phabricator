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

    $blueprint_phids = $repository->getAutomationBlueprintPHIDs();

    $is_authorized = DrydockAuthorizationQuery::isFullyAuthorized(
      $repository->getPHID(),
      $blueprint_phids);
    if (!$is_authorized) {
      return 'fa-exclamation-triangle';
    }

    return 'fa-truck';
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

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $can_test = $can_edit && $repository->canPerformAutomation();

    $automation_uri = $this->getEditPageURI();
    $test_uri = $repository->getPathURI('edit/testautomation/');

    $edit = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-pencil')
      ->setText(pht('Edit'))
      ->setHref($automation_uri)
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    $test = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-gamepad')
      ->setText(pht('Test Config'))
      ->setWorkflow(true)
      ->setDisabled(!$can_test)
      ->setHref($test_uri);

    return $this->newBox(pht('Automation'), $view, array($edit, $test));
  }

}
