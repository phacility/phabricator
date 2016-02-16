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

      $view = id(new DiffusionBranchTableView())
        ->setUser($viewer)
        ->setBranches($branches)
        ->setCommits($commits)
        ->setDiffusionRequest($drequest);

      $panel = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Branches'))
        ->setTable($view);

      $content = $panel;
    }

    $crumbs = $this->buildCrumbs(
      array(
        'branches' => true,
      ));

    $pager_box = $this->renderTablePagerBox($pager);

    return $this->newPage()
      ->setTitle(
        array(
          pht('Branches'),
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $content,
          $pager_box,
        ));
  }

}
