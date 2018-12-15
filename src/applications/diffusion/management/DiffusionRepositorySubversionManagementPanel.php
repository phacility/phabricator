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
      return 'fa-folder';
    } else {
      return 'fa-folder grey';
    }
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'importOnly',
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

    $subversion_uri = $this->getEditPageURI();

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Properties'))
        ->setHref($subversion_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $this->newCurtainView($action_list)
      ->setActionList($action_list);
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $default_branch = nonempty(
      $repository->getDetail('svn-subpath'),
      phutil_tag('em', array(), pht('Import Entire Repository')));
    $view->addProperty(pht('Import Only'), $default_branch);

    return $this->newBox(pht('Subversion'), $view);
  }

}
