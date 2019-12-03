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
      $repository->getFetchRules() ||
      $repository->getTrackOnlyRules() ||
      $repository->getPermanentRefRules();

    if ($has_any) {
      return 'fa-code-fork';
    } else {
      return 'fa-code-fork grey';
    }
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'defaultBranch',
      'fetchRefs',
      'permanentRefs',
      'trackOnly',
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

    $branches_uri = $this->getEditPageURI();

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Branches'))
        ->setHref($branches_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'user' => $viewer,
        'repository' => $repository,
      ));

    $view_uri = $drequest->generateURI(
      array(
        'action' => 'branches',
      ));

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-code-fork')
        ->setName(pht('View Branches'))
        ->setHref($view_uri));

    return $this->newCurtainView()
      ->setActionList($action_list);
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();
    $content = array();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $default_branch = nonempty(
      $repository->getDetail('default-branch'),
      phutil_tag('em', array(), $repository->getDefaultBranch()));
    $view->addProperty(pht('Default Branch'), $default_branch);

    if ($repository->supportsFetchRules()) {
      $fetch_only = $repository->getFetchRules();
      if ($fetch_only) {
        $fetch_display = implode(', ', $fetch_only);
      } else {
        $fetch_display = phutil_tag('em', array(), pht('Fetch All Refs'));
      }
      $view->addProperty(pht('Fetch Refs'), $fetch_display);
    }

    $track_only_rules = $repository->getTrackOnlyRules();
    if ($track_only_rules) {
      $track_only_rules = implode(', ', $track_only_rules);
      $view->addProperty(pht('Track Only'), $track_only_rules);
    }

    $publishing_disabled = $repository->isPublishingDisabled();
    if ($publishing_disabled) {
      $permanent_display =
        phutil_tag('em', array(), pht('Publishing Disabled'));
    } else {
      $permanent_rules = $repository->getPermanentRefRules();
      if ($permanent_rules) {
        $permanent_display = implode(', ', $permanent_rules);
      } else {
        $permanent_display = phutil_tag('em', array(), pht('All Branches'));
      }
    }
    $view->addProperty(pht('Permanent Refs'), $permanent_display);

    $content[] = $this->newBox(pht('Branches'), $view);

    return $content;
  }

}
