<?php

final class DiffusionRepositorySubversionManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'subversion';

  public function getManagementPanelLabel() {
    return pht('Subversion');
  }

  public function getManagementPanelOrder() {
    return 1000;
  }

  public function shouldEnableForRepository(
    PhabricatorRepository $repository) {
    return $repository->isSVN();
  }

  public function getManagementPanelIcon() {
    return 'fa-folder';
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'importOnly',
    );
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $default_branch = nonempty(
      $repository->getHumanReadableDetail('svn-subpath'),
      phutil_tag('em', array(), pht('Import Entire Repository')));
    $view->addProperty(pht('Import Only'), $default_branch);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $subversion_uri = $this->getEditPageURI();

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-pencil')
      ->setText(pht('Edit'))
      ->setHref($subversion_uri)
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    return $this->newBox(pht('Subversion'), $view, array($button));
  }

}
