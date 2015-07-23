<?php

final class DiffusionTagListController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  protected function processDiffusionRequest(AphrontRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $viewer = $request->getUser();

    $repository = $drequest->getRepository();

    $pager = new PHUIPagerView();
    $pager->setURI($request->getRequestURI(), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $params = array(
      'limit' => $pager->getPageSize() + 1,
      'offset' => $pager->getOffset(),
    );

    if ($drequest->getSymbolicCommit()) {
      $is_commit = true;
      $params['commit'] = $drequest->getSymbolicCommit();
    } else {
      $is_commit = false;
    }

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $tags = array();
        break;
      default:
        $conduit_result = $this->callConduitWithDiffusionRequest(
          'diffusion.tagsquery',
          $params);
        $tags = DiffusionRepositoryTag::newFromConduit($conduit_result);
        break;
    }
    $tags = $pager->sliceResults($tags);

    $content = null;
    if (!$tags) {
      $content = $this->renderStatusMessage(
        pht('No Tags'),
        $is_commit
          ? pht('This commit has no tags.')
          : pht('This repository has no tags.'));
    } else {
      $commits = id(new DiffusionCommitQuery())
        ->setViewer($viewer)
        ->withRepository($repository)
        ->withIdentifiers(mpull($tags, 'getCommitIdentifier'))
        ->needCommitData(true)
        ->execute();

      $view = id(new DiffusionTagListView())
        ->setTags($tags)
        ->setUser($viewer)
        ->setCommits($commits)
        ->setDiffusionRequest($drequest);

      $phids = $view->getRequiredHandlePHIDs();
      $handles = $this->loadViewerHandles($phids);
      $view->setHandles($handles);

      $panel = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Tags'))
        ->appendChild($view);

      $content = $panel;
    }

    $crumbs = $this->buildCrumbs(
      array(
        'tags' => true,
        'commit' => $drequest->getSymbolicCommit(),
      ));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
        $pager,
      ),
      array(
        'title' => array(
          pht('Tags'),
          pht('%s Repository', $repository->getCallsign()),
        ),
      ));
  }

}
