<?php

final class DiffusionBranchTableController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();
    $user = $request->getUser();

    $repository = $drequest->getRepository();

    $pager = new AphrontPagerView();
    $pager->setURI($request->getRequestURI(), 'offset');
    $pager->setOffset($request->getInt('offset'));

    // TODO: Add support for branches that contain commit
    $branches = DiffusionBranchInformation::newFromConduit(
      $this->callConduitWithDiffusionRequest(
        'diffusion.branchquery',
        array(
          'offset' => $pager->getOffset(),
          'limit' => $pager->getPageSize() + 1
        )));
    $branches = $pager->sliceResults($branches);

    $content = null;
    if (!$branches) {
      $content = new AphrontErrorView();
      $content->setTitle(pht('No Branches'));
      $content->appendChild(pht('This repository has no branches.'));
      $content->setSeverity(AphrontErrorView::SEVERITY_NODATA);
    } else {
      $commits = id(new PhabricatorAuditCommitQuery())
        ->withIdentifiers(
          $drequest->getRepository()->getID(),
          mpull($branches, 'getHeadCommitIdentifier'))
        ->needCommitData(true)
        ->execute();

      $view = id(new DiffusionBranchTableView())
        ->setBranches($branches)
        ->setUser($user)
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
        'branches'    => true,
      ));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
      ),
      array(
        'title' => array(
          'Branches',
          $repository->getCallsign().' Repository',
        ),
      ));
  }

}
