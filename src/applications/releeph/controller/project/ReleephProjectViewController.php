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
      ->setPreface($this->renderPreface())
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

    $list = id(new PHUIObjectItemListView())
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

      $item = id(new PHUIObjectItemView())
        ->setHeader($branch->getDisplayName())
        ->setHref($branch->getURI())
        ->addAttribute($branch_link);

      if (!$branch->getIsActive()) {
        $item->setDisabled(true);
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

  private function renderPreface() {
    $project = $this->getReleephProject();
    $viewer = $this->getRequest()->getUser();

    $id = $project->getID();

    $header = id(new PHUIHeaderView())
      ->setHeader($project->getName());

    if (!$project->getIsActive()) {
      $header->addTag(
        id(new PhabricatorTagView())
          ->setType(PhabricatorTagView::TYPE_STATE)
          ->setBackgroundColor(PhabricatorTagView::COLOR_BLACK)
          ->setName(pht('Deactivated')));
    }

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($project)
      ->setObjectURI($this->getRequest()->getRequestURI());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $this->getApplicationURI("project/{$id}/edit/");

    $deactivate_uri = "project/{$id}/action/deactivate/";
    $deactivate_uri = $this->getApplicationURI($deactivate_uri);

    $reactivate_uri = "project/{$id}/action/activate/";
    $reactivate_uri = $this->getApplicationURI($reactivate_uri);

    $history_uri = $this->getApplicationURI("project/{$id}/history/");

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Project'))
        ->setHref($edit_uri)
        ->setIcon('edit')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($project->getIsActive()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Deactivate Project'))
          ->setHref($deactivate_uri)
          ->setIcon('delete')
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Reactivate Project'))
          ->setHref($reactivate_uri)
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
      ->setObject($project);

    $properties->addProperty(
      pht('Repository'),
      $project->getRepository()->getName());

    $properties->setActionList($actions);

    $pushers = $project->getPushers();
    if ($pushers) {
      $this->loadHandles($pushers);
      $properties->addProperty(
        pht('Pushers'),
        $this->renderHandlesForPHIDs($pushers));
    }

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);
  }

}
