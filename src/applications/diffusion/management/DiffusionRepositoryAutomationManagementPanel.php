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

  protected function buildManagementPanelActions() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $can_test = $can_edit && $repository->canPerformAutomation();

    $automation_uri = $repository->getPathURI('edit/automation/');
    $test_uri = $repository->getPathURI('edit/testautomation/');

    return array(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Automation'))
        ->setHref($automation_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit),
      id(new PhabricatorActionView())
        ->setIcon('fa-gamepad')
        ->setName(pht('Test Configuration'))
        ->setWorkflow(true)
        ->setDisabled(!$can_test)
        ->setHref($test_uri),
    );
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer)
      ->setActionList($this->newActions());

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
