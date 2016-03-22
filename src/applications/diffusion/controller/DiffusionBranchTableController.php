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
    );

    $contains = $drequest->getSymbolicCommit();
    if (strlen($contains)) {
      $params['contains'] = $contains;
    }

    $branches = $this->callConduitWithDiffusionRequest(
      'diffusion.branchquery',
      $params);
    $branches = $pager->sliceResults($branches);

    $branches = DiffusionRepositoryRef::loadAllFromDictionaries($branches);

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

      $table = id(new DiffusionBranchTableView())
        ->setUser($viewer)
        ->setBranches($branches)
        ->setCommits($commits)
        ->setDiffusionRequest($drequest);

      $content = id(new PHUIObjectBoxView())
        ->setHeaderText($repository->getName())
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->setTable($table);
    }

    $crumbs = $this->buildCrumbs(
      array(
        'branches' => true,
      ));
    $crumbs->setBorder(true);

    $pager_box = $this->renderTablePagerBox($pager);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Branches'))
      ->setHeaderIcon('fa-code-fork');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
          $content,
          $pager_box,
      ));

    return $this->newPage()
      ->setTitle(
        array(
          pht('Branches'),
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
        ));
  }

}
