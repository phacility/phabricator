<?php

final class ReleephBranchViewController extends ReleephBranchController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;
  private $branchID;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->branchID = $data['branchID'];
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $branch = id(new ReleephBranchQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->branchID))
      ->executeOne();
    if (!$branch) {
      return new Aphront404Response();
    }
    $this->setBranch($branch);

    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setPreface($this->renderPreface())
      ->setQueryKey($this->queryKey)
      ->setSearchEngine($this->getSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $requests,
    PhabricatorSavedQuery $query) {

    assert_instances_of($requests, 'ReleephRequest');
    $viewer = $this->getRequest()->getUser();

    $branch = $this->getBranch();

    // TODO: Really really gross.
    $branch->populateReleephRequestHandles(
      $viewer,
      $requests);

    $list = id(new ReleephRequestHeaderListView())
      ->setOriginType('branch')
      ->setUser($viewer)
      ->setAphrontRequest($this->getRequest())
      ->setReleephProject($branch->getProduct())
      ->setReleephBranch($branch)
      ->setReleephRequests($requests);

    return $list;
  }

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $this->getSearchEngine()->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  private function getSearchEngine() {
    $branch = $this->getBranch();
    return id(new ReleephRequestSearchEngine())
      ->setBranch($branch)
      ->setBaseURI($this->getApplicationURI('branch/'.$branch->getID().'/'))
      ->setViewer($this->getRequest()->getUser());
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $branch = $this->getBranch();
    $create_uri = $branch->getURI('request/');
    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setHref($create_uri)
        ->setName(pht('New Pull Request'))
        ->setIcon('create')
        ->setDisabled(!$branch->isActive()));

    return $crumbs;
  }

  private function renderPreface() {
    $viewer = $this->getRequest()->getUser();

    $branch = $this->getBranch();
    $id = $branch->getID();

    $header = id(new PHUIHeaderView())
      ->setHeader($branch->getDisplayName())
      ->setUser($viewer)
      ->setPolicyObject($branch);

    if ($branch->getIsActive()) {
      $header->setStatus('oh-ok', '', pht('Active'));
    } else {
      $header->setStatus('policy-noone', '', pht('Closed'));
    }

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($branch)
      ->setObjectURI($this->getRequest()->getRequestURI());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $branch,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $this->getApplicationURI("branch/edit/{$id}/");
    $close_uri = $this->getApplicationURI("branch/close/{$id}/");
    $reopen_uri = $this->getApplicationURI("branch/re-open/{$id}/");
    $history_uri = $this->getApplicationURI("branch/{$id}/history/");

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Branch'))
        ->setHref($edit_uri)
        ->setIcon('edit')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($branch->getIsActive()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Close Branch'))
          ->setHref($close_uri)
          ->setIcon('delete')
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Reopen Branch'))
          ->setHref($reopen_uri)
          ->setIcon('new')
          ->setUser($viewer)
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    }

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View History'))
        ->setHref($history_uri)
        ->setIcon('transcript'));

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($branch)
      ->setActionList($actions);

    $properties->addProperty(
      pht('Branch'),
      $branch->getName());

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);
  }

}
