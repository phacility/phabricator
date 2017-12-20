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

  public function shouldEnableForRepository(
    PhabricatorRepository $repository) {
    return $repository->isGit();
  }


  public function getManagementPanelIcon() {
    return 'fa-upload';
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'stagingAreaURI',
    );
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

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $staging_uri = $this->getEditPageURI();

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-pencil')
      ->setText(pht('Edit'))
      ->setHref($staging_uri)
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    return $this->newBox(pht('Staging Area'), $view, array($button));
  }

}
