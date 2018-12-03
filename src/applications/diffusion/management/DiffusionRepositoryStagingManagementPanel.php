<?php

final class DiffusionRepositoryStagingManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'staging';

  public function getManagementPanelLabel() {
    return pht('Staging Area');
  }

  public function getManagementPanelOrder() {
    return 700;
  }

  public function getManagementPanelGroupKey() {
    return DiffusionRepositoryManagementBuildsPanelGroup::PANELGROUPKEY;
  }

  public function shouldEnableForRepository(
    PhabricatorRepository $repository) {
    return $repository->isGit();
  }

  public function getManagementPanelIcon() {
    $repository = $this->getRepository();

    $staging_uri = $repository->getStagingURI();

    if ($staging_uri) {
      return 'fa-upload';
    } else {
      return 'fa-upload grey';
    }
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'stagingAreaURI',
    );
  }

  public function buildManagementPanelCurtain() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();
    $action_list = $this->newActionList();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $staging_uri = $this->getEditPageURI();

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Staging'))
        ->setHref($staging_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $this->newCurtainView()
      ->setActionList($action_list);
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $staging_uri = $repository->getStagingURI();
    if (!$staging_uri) {
      $staging_uri = phutil_tag('em', array(), pht('No Staging Area'));
    }

    $view->addProperty(pht('Staging Area URI'), $staging_uri);

    return $this->newBox(pht('Staging Area'), $view);
  }

}
