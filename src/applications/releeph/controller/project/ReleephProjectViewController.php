<?php

final class ReleephProjectViewController extends ReleephProjectController
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
      ->setSearchEngine(
        id(new ReleephBranchSearchEngine())
          ->setProjectID($this->getReleephProject()->getID()))
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $branches,
    PhabricatorSavedQuery $saved) {
    assert_instances_of($branches, 'ReleephBranch');

    $viewer = $this->getRequest()->getUser();

    $projects = mpull($branches, 'getProject');
    $repo_phids = mpull($projects, 'getRepositoryPHID');

    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withPHIDs($repo_phids)
      ->execute();
    $repos = mpull($repos, null, 'getPHID');

    $phids = mpull($branches, 'getCreatedByUserPHID');
    $this->loadHandles($phids);

    $requests = array();
    if ($branches) {
      $requests = id(new ReleephRequestQuery())
        ->setViewer($viewer)
        ->withBranchIDs(mpull($branches, 'getID'))
        ->withStatus(ReleephRequestQuery::STATUS_OPEN)
        ->execute();
      $requests = mgroup($requests, 'getBranchID');
    }

    $list = id(new PhabricatorObjectItemListView())
      ->setUser($viewer);
    foreach ($branches as $branch) {
      $diffusion_href = null;
      $repo = idx($repos, $branch->getProject()->getRepositoryPHID());
      if ($repo) {
        $drequest = DiffusionRequest::newFromDictionary(
          array(
            'user' => $viewer,
            'repository' => $repo,
          ));

        $diffusion_href = $drequest->generateURI(
          array(
            'action' => 'branch',
            'branch' => $branch->getName(),
          ));
      }

      $branch_link = $branch->getName();
      if ($diffusion_href) {
        $branch_link = phutil_tag(
          'a',
          array(
            'href' => $diffusion_href,
          ),
          $branch_link);
      }

      $item = id(new PhabricatorObjectItemView())
        ->setHeader($branch->getDisplayName())
        ->setHref($branch->getURI())
        ->addAttribute($branch_link);

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('edit')
          ->setHref($branch->getURI('edit/')));

      if ($branch->getIsActive()) {
        $item->setBarColor('blue');
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('delete')
            ->setWorkflow(true)
            ->setHref($branch->getURI('close/')));
      } else {
        $item->setDisabled(true);
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('enable')
            ->setWorkflow(true)
            ->setHref($branch->getURI('re-open/')));
      }

      $commit = $branch->getCutPointCommit();
      if ($commit) {
        $item->addIcon(
          'none',
          phabricator_datetime($commit->getEpoch(), $viewer));
      }

      $open_count = count(idx($requests, $branch->getID(), array()));
      if ($open_count) {
        $item->setBarColor('orange');
        $item->addIcon(
          'fork',
          pht('%d Open Pull Request(s)', new PhutilNumber($open_count)));
      }

      $list->addItem($item);
    }

    return $list;
  }

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('project/create/', pht('Create Project'));
    }

    id(new ReleephBranchSearchEngine())
      ->setProjectID($this->getReleephProject()->getID())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $project = $this->getReleephProject();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setHref($project->getURI('cutbranch'))
        ->setName(pht('Cut New Branch'))
        ->setIcon('create'));

    return $crumbs;
  }

}
