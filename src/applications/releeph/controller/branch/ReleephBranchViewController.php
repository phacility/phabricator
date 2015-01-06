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

    $controller = id(new PhabricatorApplicationSearchController())
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

    // TODO: This is generally a bit sketchy, but we don't do this kind of
    // thing elsewhere at the moment. For the moment it shouldn't be hugely
    // costly, and we can batch things later. Generally, this commits fewer
    // sins than the old code did.

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);

    $list = array();
    foreach ($requests as $pull) {
      $field_list = PhabricatorCustomField::getObjectFields(
        $pull,
        PhabricatorCustomField::ROLE_VIEW);

      $field_list
        ->setViewer($viewer)
        ->readFieldsFromStorage($pull);

      foreach ($field_list->getFields() as $field) {
        if ($field->shouldMarkup()) {
          $field->setMarkupEngine($engine);
        }
      }

      $list[] = id(new ReleephRequestView())
        ->setUser($viewer)
        ->setCustomFields($field_list)
        ->setPullRequest($pull)
        ->setIsListView(true);
    }

    // This is quite sketchy, but the list has not actually rendered yet, so
    // this still allows us to batch the markup rendering.
    $engine->process();

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

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $branch = $this->getBranch();
    if ($branch) {
      $pull_uri = $this->getApplicationURI('branch/pull/'.$branch->getID().'/');
      $crumbs->addAction(
        id(new PHUIListItemView())
          ->setHref($pull_uri)
          ->setName(pht('New Pull Request'))
          ->setIcon('fa-plus-square')
          ->setDisabled(!$branch->isActive()));
    }

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
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'dark', pht('Closed'));
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
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($branch->getIsActive()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Close Branch'))
          ->setHref($close_uri)
          ->setIcon('fa-times')
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Reopen Branch'))
          ->setHref($reopen_uri)
          ->setIcon('fa-plus')
          ->setUser($viewer)
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    }

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View History'))
        ->setHref($history_uri)
        ->setIcon('fa-list'));

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
