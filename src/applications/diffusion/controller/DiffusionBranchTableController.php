<?php

final class DiffusionBranchTableController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $pager = id(new PHUIPagerView())
      ->readFromRequest($request);

    $params = array(
      'offset' => $pager->getOffset(),
      'limit' => $pager->getPageSize() + 1,
      'branch' => null,
    );

    $contains = $drequest->getSymbolicCommit();
    if ($contains !== null && strlen($contains)) {
      $params['contains'] = $contains;
    }

    $branches = $this->callConduitWithDiffusionRequest(
      'diffusion.branchquery',
      $params);
    $branches = $pager->sliceResults($branches);

    $branches = DiffusionRepositoryRef::loadAllFromDictionaries($branches);

    // If there is one page of results or fewer, sort branches so the default
    // branch is on top and permanent branches are below it.
    if (!$pager->getOffset() && !$pager->getHasMorePages()) {
      $branches = $this->sortBranches($repository, $branches);
    }

    $content = null;
    if (!$branches) {
      $content = $this->renderStatusMessage(
        pht('No Branches'),
        pht('This repository has no branches.'));
    } else {
      $commits = id(new DiffusionCommitQuery())
        ->setViewer($viewer)
        ->withIdentifiers(mpull($branches, 'getCommitIdentifier'))
        ->withRepository($repository)
        ->execute();

      $list = id(new DiffusionBranchListView())
        ->setUser($viewer)
        ->setBranches($branches)
        ->setCommits($commits)
        ->setDiffusionRequest($drequest);

      $content = id(new PHUIObjectBoxView())
        ->setHeaderText($repository->getName())
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->addClass('diffusion-mobile-view')
        ->setTable($list)
        ->setPager($pager);
    }

    $crumbs = $this->buildCrumbs(
      array(
        'branches' => true,
      ));
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Branches'))
      ->setHeaderIcon('fa-code-fork');

    if (!$repository->isSVN()) {
      $branch_tag = $this->renderBranchTag($drequest);
      $header->addTag($branch_tag);
    }

    $tabs = $this->buildTabsView('branch');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setTabs($tabs)
      ->setFooter(array(
          $content,
      ));

    return $this->newPage()
      ->setTitle(
        array(
          pht('Branches'),
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function sortBranches(
    PhabricatorRepository $repository,
    array $branches) {

    $publisher = $repository->newPublisher();
    $default_branch = $repository->getDefaultBranch();

    $vectors = array();
    foreach ($branches as $key => $branch) {
      $short_name = $branch->getShortName();

      if ($short_name === $default_branch) {
        $order_default = 0;
      } else {
        $order_default = 1;
      }

      if ($publisher->shouldPublishRef($branch)) {
        $order_permanent = 0;
      } else {
        $order_permanent = 1;
      }

      $vectors[$key] = id(new PhutilSortVector())
        ->addInt($order_default)
        ->addInt($order_permanent)
        ->addString($short_name);
    }

    $vectors = msortv($vectors, 'getSelf');

    return array_select_keys($branches, array_keys($vectors));
  }

}
