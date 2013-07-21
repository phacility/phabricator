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

    $list = id(new PhabricatorObjectItemListView())
      ->setUser($viewer);

    foreach ($projects as $project) {
      $id = $project->getID();

      $item = id(new PhabricatorObjectItemView())
        ->setHeader($project->getName())
        ->setHref($this->getApplicationURI("project/{$id}/"));

      $edit_uri = $this->getApplicationURI("project/{$id}/edit/");
      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('edit')
          ->setHref($edit_uri));

      if ($project->getIsActive()) {
        $disable_uri = $this->getApplicationURI(
          "project/{$id}/action/deactivate/");

        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('delete')
            ->setName(pht('Deactivate'))
            ->setWorkflow(true)
            ->setHref($disable_uri));
      } else {
        $enable_uri = $this->getApplicationURI(
          "project/{$id}/action/activate/");

        $item->setDisabled(true);
        $item->addIcon('none', pht('Inactive'));
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('new')
            ->setName(pht('Reactivate'))
            ->setWorkflow(true)
            ->setHref($enable_uri));
      }

      // TODO: See T3551.

      $repo = $project->loadPhabricatorRepository();
      if ($repo) {
        $item->addAttribute(
          phutil_tag(
            'a',
            array(
              'href' => '/diffusion/'.$repo->getCallsign().'/',
            ),
            'r'.$repo->getCallsign()));
      }

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
