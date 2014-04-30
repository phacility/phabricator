<?php

final class PhabricatorAuditListController
  extends PhabricatorAuditController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;
  private $name;
  private $filterStatus;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new PhabricatorCommitSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $commits,
    PhabricatorSavedQuery $query) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');

    $viewer = $this->getRequest()->getUser();
    $nodata = pht('No matching audits.');
    $view = id(new PhabricatorAuditListView())
      ->setUser($viewer)
      ->setCommits($commits)
      ->setAuthorityPHIDs(
        PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($viewer))
      ->setNoDataString($nodata);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($phids);
    $view->setHandles($handles);
    return $view->buildList();
  }
}
