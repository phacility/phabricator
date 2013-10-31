<?php

final class DiffusionTagListController extends DiffusionController {

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

    $params = array(
      'limit' => $pager->getPageSize() + 1,
      'offset' => $pager->getOffset());
    if ($drequest->getRawCommit()) {
      $is_commit = true;
      $params['commit'] = $drequest->getRawCommit();
    } else {
      $is_commit = false;
    }

    $tags = array();
    try {
      $conduit_result = $this->callConduitWithDiffusionRequest(
        'diffusion.tagsquery',
        $params);
      $tags = DiffusionRepositoryTag::newFromConduit($conduit_result);
    } catch (ConduitException $ex) {
      if ($ex->getMessage() != 'ERR-UNSUPPORTED-VCS') {
        throw $ex;
      }
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
        ->withRepositoryIDs(array($repository->getID()))
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

      $panel = id(new AphrontPanelView())
        ->setNoBackground(true)
        ->appendChild($view)
        ->appendChild($pager);

      $content = $panel;
    }

    $crumbs = $this->buildCrumbs(
      array(
        'tags' => true,
        'commit' => $drequest->getRawCommit(),
      ));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
      ),
      array(
        'title' => array(
          pht('Tags'),
          $repository->getCallsign().' Repository',
        ),
      ));
  }

}
