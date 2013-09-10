<?php

final class ManiphestTaskListControllerPro
  extends ManiphestController
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
      ->setSearchEngine(new ManiphestTaskSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $tasks,
    PhabricatorSavedQuery $query) {
    assert_instances_of($tasks, 'ManiphestTask');

    $viewer = $this->getRequest()->getUser();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($tasks as $task) {
      $item = id(new PHUIObjectItemView())
        ->setObjectName('T'.$task->getID())
        ->setHeader($task->getTitle())
        ->setHref('/T'.$task->getID())
        ->setObject($task);

      $list->addItem($item);
    }

    return $list;
  }

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('create', pht('Create Task'));
    }

    id(new ManiphestTaskSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

}
