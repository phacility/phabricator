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
    $repository = $this->getRepository();

    $has_any = (bool)$repository->getDetail('svn-subpath');

    if ($has_any) {
      return 'fa-database';
    } else {
      return 'fa-database grey';
    }
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'importOnly',
    );
  }

  protected function buildManagementPanelActions() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $subversion_uri = $this->getEditPageURI();

    return array(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Properties'))
        ->setHref($subversion_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit),
    );
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer)
      ->setActionList($this->newActions());

    $default_branch = nonempty(
      $repository->getHumanReadableDetail('svn-subpath'),
      phutil_tag('em', array(), pht('Import Entire Repository')));
    $view->addProperty(pht('Import Only'), $default_branch);


    return $this->newBox(pht('Subversion'), $view);
  }

}
