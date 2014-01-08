<?php

final class DiffusionRepositoryListController extends DiffusionController
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
      ->setSearchEngine(new PhabricatorRepositorySearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $repositories,
    PhabricatorSavedQuery $query) {
    assert_instances_of($repositories, 'PhabricatorRepository');

    $viewer = $this->getRequest()->getUser();

    $project_phids = array_fuse(
      array_mergev(
        mpull($repositories, 'getProjectPHIDs')));
    $project_handles = $this->loadViewerHandles($project_phids);

    $list = new PHUIObjectItemListView();
    foreach ($repositories as $repository) {
      $id = $repository->getID();

      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setHeader($repository->getName())
        ->setHref($this->getApplicationURI($repository->getCallsign().'/'));

      $commit = $repository->getMostRecentCommit();
      if ($commit) {
        $commit_link = DiffusionView::linkCommit(
            $repository,
            $commit->getCommitIdentifier(),
            $commit->getSummary());
        $item->setSubhead($commit_link);
        $item->setEpoch($commit->getEpoch());
      }

      $item->addIcon(
        'none',
        PhabricatorRepositoryType::getNameForRepositoryType(
          $repository->getVersionControlSystem()));

      $size = $repository->getCommitCount();
      if ($size) {
        $history_uri = DiffusionRequest::generateDiffusionURI(
          array(
            'callsign' => $repository->getCallsign(),
            'action' => 'history',
          ));

        $item->addAttribute(
          phutil_tag(
            'a',
            array(
              'href' => $history_uri,
            ),
            pht('%s Commit(s)', new PhutilNumber($size))));
      } else {
        $item->addAttribute(pht('No Commits'));
      }

      $handles = array_select_keys(
        $project_handles,
        $repository->getProjectPHIDs());
      if ($handles) {
        $item->addAttribute(
          id(new ManiphestTaskProjectsView())
            ->setHandles($handles));
      }

      if (!$repository->isTracked()) {
        $item->setDisabled(true);
        $item->addIcon('disable-grey', pht('Inactive'));
      }

      $list->addItem($item);
    }

    return $list;
  }

  public function buildSideNavView($for_app = false) {
    $viewer = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhabricatorRepositorySearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $can_create = $this->hasApplicationCapability(
      DiffusionCapabilityCreateRepositories::CAPABILITY);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Repository'))
        ->setHref($this->getApplicationURI('new/'))
        ->setDisabled(!$can_create)
        ->setIcon('create'));

    return $crumbs;
  }

}
