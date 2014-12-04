<?php

final class DiffusionBranchTableController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $repository = $drequest->getRepository();

    $pager = new AphrontPagerView();
    $pager->setURI($request->getRequestURI(), 'offset');
    $pager->setOffset($request->getInt('offset'));

    // TODO: Add support for branches that contain commit
    $branches = $this->callConduitWithDiffusionRequest(
      'diffusion.branchquery',
      array(
        'offset' => $pager->getOffset(),
        'limit' => $pager->getPageSize() + 1,
      ));
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

      $panel = id(new AphrontPanelView())
        ->setNoBackground(true)
        ->appendChild($view)
        ->appendChild($pager);

      $content = $panel;
    }

    $crumbs = $this->buildCrumbs(
      array(
        'branches' => true,
      ));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
      ),
      array(
        'title' => array(
          pht('Branches'),
          'r'.$repository->getCallsign(),
        ),
      ));
  }

}
