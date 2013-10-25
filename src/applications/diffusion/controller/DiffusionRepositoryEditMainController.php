<?php

final class DiffusionRepositoryEditMainController
  extends DiffusionRepositoryEditController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $drequest = $this->diffusionRequest;
    $repository = $drequest->getRepository();

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $is_svn = false;
    $is_git = false;
    $is_hg = false;
    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $is_git = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $is_svn = true;
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $is_hg = true;
        break;
    }

    $has_branches = ($is_git || $is_hg);

    $crumbs = $this->buildApplicationCrumbs();

    $title = pht('Edit %s', $repository->getName());

    $header = id(new PHUIHeaderView())
      ->setHeader($title);
    if (!$repository->isTracked()) {
      $header->addTag(
        id(new PhabricatorTagView())
          ->setType(PhabricatorTagView::TYPE_STATE)
          ->setName(pht('Inactive'))
          ->setBackgroundColor(PhabricatorTagView::COLOR_BLACK));
    }

    $basic_actions = $this->buildBasicActions($repository);
    $basic_properties =
      $this->buildBasicProperties($repository, $basic_actions);

    $policy_actions = $this->buildPolicyActions($repository);
    $policy_properties =
      $this->buildPolicyProperties($repository, $policy_actions);

    $remote_properties = $this->buildRemoteProperties(
      $repository,
      $this->buildRemoteActions($repository));

    $encoding_actions = $this->buildEncodingActions($repository);
    $encoding_properties =
      $this->buildEncodingProperties($repository, $encoding_actions);

    $branches_properties = null;
    if ($has_branches) {
      $branches_properties = $this->buildBranchesProperties(
        $repository,
        $this->buildBranchesActions($repository));
    }

    $subversion_properties = null;
    if ($is_svn) {
      $subversion_properties = $this->buildSubversionProperties(
        $repository,
        $this->buildSubversionActions($repository));
    }

    $actions_properties = $this->buildActionsProperties(
      $repository,
      $this->buildActionsActions($repository));

    $xactions = id(new PhabricatorRepositoryTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($repository->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);
    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $xaction_view = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($repository->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $obj_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($basic_properties)
      ->addPropertyList($policy_properties)
      ->addPropertyList($remote_properties)
      ->addPropertyList($encoding_properties);

    if ($branches_properties) {
      $obj_box->addPropertyList($branches_properties);
    }

    if ($subversion_properties) {
      $obj_box->addPropertyList($subversion_properties);
    }

    $obj_box->addPropertyList($actions_properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $obj_box,
        $xaction_view,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildBasicActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('edit')
      ->setName(pht('Edit Basic Information'))
      ->setHref($this->getRepositoryControllerURI($repository, 'edit/basic/'));
    $view->addAction($edit);

    $activate = id(new PhabricatorActionView())
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/activate/'))
      ->setWorkflow(true);

    if ($repository->isTracked()) {
      $activate
        ->setIcon('disable')
        ->setName(pht('Deactivate Repository'));
    } else {
      $activate
        ->setIcon('enable')
        ->setName(pht('Activate Repository'));
    }

    $view->addAction($activate);

    return $view;
  }

  private function buildBasicProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $view->addProperty(pht('Name'), $repository->getName());
    $view->addProperty(pht('ID'), $repository->getID());
    $view->addProperty(pht('PHID'), $repository->getPHID());

    $type = PhabricatorRepositoryType::getNameForRepositoryType(
      $repository->getVersionControlSystem());

    $view->addProperty(pht('Type'), $type);
    $view->addProperty(pht('Callsign'), $repository->getCallsign());

    $description = $repository->getDetail('description');
    $view->addSectionHeader(pht('Description'));
    if (!strlen($description)) {
      $description = phutil_tag('em', array(), pht('No description provided.'));
    } else {
      $description = PhabricatorMarkupEngine::renderOneObject(
        $repository,
        'description',
        $viewer);
    }
    $view->addTextContent($description);

    return $view;
  }

  private function buildEncodingActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('edit')
      ->setName(pht('Edit Text Encoding'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/encoding/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildEncodingProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions)
      ->addSectionHeader(pht('Text Encoding'));

    $encoding = $repository->getDetail('encoding');
    if (!$encoding) {
      $encoding = phutil_tag('em', array(), pht('Use Default (UTF-8)'));
    }

    $view->addProperty(pht('Encoding'), $encoding);

    return $view;
  }

  private function buildPolicyActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('edit')
      ->setName(pht('Edit Policies'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/policy/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildPolicyProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions)
      ->addSectionHeader(pht('Policies'));

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $repository);

    $view->addProperty(
      pht('Visible To'),
      $descriptions[PhabricatorPolicyCapability::CAN_VIEW]);

    $view->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);


    return $view;
  }

  private function buildBranchesActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('edit')
      ->setName(pht('Edit Branches'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/branches/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildBranchesProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions)
      ->addSectionHeader(pht('Branches'));

    $default_branch = nonempty(
      $repository->getHumanReadableDetail('default-branch'),
      phutil_tag('em', array(), $repository->getDefaultBranch()));
    $view->addProperty(pht('Default Branch'), $default_branch);

    $track_only = nonempty(
      $repository->getHumanReadableDetail('branch-filter'),
      phutil_tag('em', array(), pht('Track All Branches')));
    $view->addProperty(pht('Track Only'), $track_only);

    $autoclose_only = nonempty(
      $repository->getHumanReadableDetail('close-commits-filter'),
      phutil_tag('em', array(), pht('Autoclose On All Branches')));
    $view->addProperty(pht('Autoclose Only'), $autoclose_only);

    return $view;
  }

  private function buildSubversionActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('edit')
      ->setName(pht('Edit Subversion Info'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/subversion/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildSubversionProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions)
      ->addSectionHeader(pht('Subversion'));

    $svn_uuid = nonempty(
      $repository->getUUID(),
      phutil_tag('em', array(), pht('Not Configured')));
    $view->addProperty(pht('Subversion UUID'), $svn_uuid);

    $svn_subpath = nonempty(
      $repository->getHumanReadableDetail('svn-subpath'),
      phutil_tag('em', array(), pht('Import Entire Repository')));
    $view->addProperty(pht('Import Only'), $svn_subpath);

    return $view;
  }

  private function buildActionsActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('edit')
      ->setName(pht('Edit Actions'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/actions/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildActionsProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions)
      ->addSectionHeader(pht('Actions'));

    $notify = $repository->getDetail('herald-disabled')
      ? pht('Off')
      : pht('On');
    $notify = phutil_tag('em', array(), $notify);
    $view->addProperty(pht('Publish/Notify'), $notify);

    $autoclose = $repository->getDetail('disable-autoclose')
      ? pht('Off')
      : pht('On');
    $autoclose = phutil_tag('em', array(), $autoclose);
    $view->addProperty(pht('Autoclose'), $autoclose);

    return $view;
  }

  private function buildRemoteActions(PhabricatorRepository $repository) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $edit = id(new PhabricatorActionView())
      ->setIcon('edit')
      ->setName(pht('Edit Remote'))
      ->setHref(
        $this->getRepositoryControllerURI($repository, 'edit/remote/'));
    $view->addAction($edit);

    return $view;
  }

  private function buildRemoteProperties(
    PhabricatorRepository $repository,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions)
      ->addSectionHeader(pht('Remote'));

    $view->addProperty(
      pht('Remote URI'),
      $repository->getDetail('remote-uri'));

    return $view;
  }

}
