<?php

final class ReleephBranchViewController extends ReleephProjectController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    parent::willProcessRequest($data);
    $this->queryKey = idx($data, 'queryKey');
  }


  public function processRequest() {
    $request = $this->getRequest();

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

    $releeph_branch = $this->getReleephBranch();
    $releeph_project = $this->getReleephProject();

    // TODO: Really gross.
    $releeph_branch->populateReleephRequestHandles(
      $viewer,
      $requests);

    $list = id(new ReleephRequestHeaderListView())
      ->setOriginType('branch')
      ->setUser($viewer)
      ->setAphrontRequest($this->getRequest())
      ->setReleephProject($releeph_project)
      ->setReleephBranch($releeph_branch)
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
    $branch = $this->getReleephBranch();
    return id(new ReleephRequestSearchEngine())
      ->setBranch($branch)
      ->setBaseURI($branch->getURI())
      ->setViewer($this->getRequest()->getUser());
  }

  public function buildApplicationCrumbs() {
    $releeph_branch = $this->getReleephBranch();

    $crumbs = parent::buildApplicationCrumbs();

    if ($releeph_branch->isActive()) {
      $create_uri = $releeph_branch->getURI('request/');
      $crumbs->addAction(
        id(new PHUIListItemView())
          ->setHref($create_uri)
          ->setName(pht('Request Pick'))
          ->setIcon('create'));
    }

    return $crumbs;
  }

  private function renderPreface() {
    $branch = $this->getReleephBranch();
    $viewer = $this->getRequest()->getUser();

    $id = $branch->getID();

    $header = id(new PHUIHeaderView())
      ->setHeader($branch->getDisplayName());

    if (!$branch->getIsActive()) {
      $header->addTag(
        id(new PHUITagView())
          ->setType(PHUITagView::TYPE_STATE)
          ->setBackgroundColor(PHUITagView::COLOR_BLACK)
          ->setName(pht('Closed')));
    }

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($branch)
      ->setObjectURI($this->getRequest()->getRequestURI());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $branch,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $branch->getURI('edit/');
    $close_uri = $branch->getURI('close/');
    $reopen_uri = $branch->getURI('re-open/');

    $id = $branch->getID();
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
          ->setRenderAsForm(true)
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
