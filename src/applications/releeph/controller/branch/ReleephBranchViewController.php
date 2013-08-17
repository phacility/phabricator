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


}
