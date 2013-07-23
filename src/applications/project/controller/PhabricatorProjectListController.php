<?php

final class PhabricatorProjectListController
  extends PhabricatorProjectController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

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
      ->setSearchEngine(new PhabricatorProjectSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $projects,
    PhabricatorSavedQuery $query) {
    assert_instances_of($projects, 'PhabricatorProject');
    $viewer = $this->getRequest()->getUser();

    $list = new PhabricatorObjectItemListView();
    $list->setUser($viewer);
    foreach ($projects as $project) {
      $id = $project->getID();

      $item = id(new PhabricatorObjectItemView())
        ->setHeader($project->getName())
        ->setHref($this->getApplicationURI("view/{$id}/"));

      if ($project->getStatus() == PhabricatorProjectStatus::STATUS_ARCHIVED) {
        $item->addIcon('delete-grey', pht('Archived'));
        $item->setDisabled(true);
      }


      $list->addItem($item);
    }

    return $list;
  }

}
