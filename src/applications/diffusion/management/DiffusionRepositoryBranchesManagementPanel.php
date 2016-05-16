<?php

final class DiffusionRepositoryBranchesManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'branches';

  public function getManagementPanelLabel() {
    return pht('Branches');
  }

  public function getManagementPanelOrder() {
    return 1000;
  }

  public function shouldEnableForRepository(
    PhabricatorRepository $repository) {
    return ($repository->isGit() || $repository->isHg());
  }

  public function getManagementPanelIcon() {
    $repository = $this->getRepository();

    $has_any =
      $repository->getDetail('default-branch') ||
      $repository->getDetail('branch-filter') ||
      $repository->getDetail('close-commits-filter');

    if ($has_any) {
      return 'fa-code-fork';
    } else {
      return 'fa-code-fork grey';
    }
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'defaultBranch',
      'trackOnly',
      'autocloseOnly',
    );
  }

  protected function buildManagementPanelActions() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $branches_uri = $this->getEditPageURI();

    return array(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Branches'))
        ->setHref($branches_uri)
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
      $repository->getHumanReadableDetail('default-branch'),
      phutil_tag('em', array(), $repository->getDefaultBranch()));
    $view->addProperty(pht('Default Branch'), $default_branch);

    $track_only = nonempty(
      $repository->getHumanReadableDetail('branch-filter', array()),
      phutil_tag('em', array(), pht('Track All Branches')));
    $view->addProperty(pht('Track Only'), $track_only);

    $autoclose_only = nonempty(
      $repository->getHumanReadableDetail('close-commits-filter', array()),
      phutil_tag('em', array(), pht('Autoclose On All Branches')));

    if ($repository->getDetail('disable-autoclose')) {
      $autoclose_only = phutil_tag('em', array(), pht('Disabled'));
    }

    $view->addProperty(pht('Autoclose Only'), $autoclose_only);

    return $this->newBox(pht('Branches'), $view);
  }

}
