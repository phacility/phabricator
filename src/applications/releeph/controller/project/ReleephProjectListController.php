<?php

final class ReleephProjectListController extends ReleephController
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
      ->setSearchEngine(new ReleephProjectSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $projects,
    PhabricatorSavedQuery $query) {
    assert_instances_of($projects, 'ReleephProject');
    $viewer = $this->getRequest()->getUser();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($projects as $project) {
      $id = $project->getID();

      $item = id(new PHUIObjectItemView())
        ->setHeader($project->getName())
        ->setHref($this->getApplicationURI("project/{$id}/"));

      if (!$project->getIsActive()) {
        $item->setDisabled(true);
        $item->addIcon('none', pht('Inactive'));
      }

      $repo = $project->getRepository();
      $item->addAttribute(
        phutil_tag(
          'a',
          array(
            'href' => '/diffusion/'.$repo->getCallsign().'/',
          ),
          'r'.$repo->getCallsign()));

      $arc = $project->loadArcanistProject();
      if ($arc) {
        $item->addAttribute($arc->getName());
      }

      $list->addItem($item);
    }

    return $list;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Project'))
        ->setHref($this->getApplicationURI('project/create/'))
        ->setIcon('create'));

    return $crumbs;
  }

}
