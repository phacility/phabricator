<?php

final class DiffusionTagListController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();
    $user = $request->getUser();

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
      $content = new AphrontErrorView();
      $content->setTitle(pht('No Tags'));
      if ($is_commit) {
        $content->appendChild(pht('This commit has no tags.'));
      } else {
        $content->appendChild(pht('This repository has no tags.'));
      }
      $content->setSeverity(AphrontErrorView::SEVERITY_NODATA);
    } else {
      $commits = id(new PhabricatorAuditCommitQuery())
        ->withIdentifiers(
          $drequest->getRepository()->getID(),
          mpull($tags, 'getCommitIdentifier'))
        ->needCommitData(true)
        ->execute();

      $view = id(new DiffusionTagListView())
        ->setTags($tags)
        ->setUser($user)
        ->setCommits($commits)
        ->setDiffusionRequest($drequest);

      $phids = $view->getRequiredHandlePHIDs();
      $handles = $this->loadViewerHandles($phids);
      $view->setHandles($handles);

      $panel = id(new AphrontPanelView())
        ->setHeader(pht('Tags'))
        ->appendChild($view)
        ->appendChild($pager);

      $content = $panel;
    }

    return $this->buildStandardPageResponse(
      array(
        $this->buildCrumbs(
          array(
            'tags'    => true,
            'commit'  => $drequest->getRawCommit(),
          )),
        $content,
      ),
      array(
        'title' => array(
          'Tags',
          $repository->getCallsign().' Repository',
        ),
      ));
  }

}
