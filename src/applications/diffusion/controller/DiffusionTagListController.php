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

    if ($drequest->getRawCommit()) {
      $is_commit = true;

      $query = DiffusionCommitTagsQuery::newFromDiffusionRequest($drequest);
      $query->setOffset($pager->getOffset());
      $query->setLimit($pager->getPageSize() + 1);
      $tags = $query->loadTags();
    } else {
      $is_commit = false;

      $query = DiffusionTagListQuery::newFromDiffusionRequest($drequest);
      $query->setOffset($pager->getOffset());
      $query->setLimit($pager->getPageSize() + 1);
      $tags = $query->loadTags();
    }

    $tags = $pager->sliceResults($tags);

    $content = null;
    if (!$tags) {
      $content = new AphrontErrorView();
      $content->setTitle('No Tags');
      if ($is_commit) {
        $content->appendChild('This commit has no tags.');
      } else {
        $content->appendChild('This repository has no tags.');
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
        ->setHeader('Tags')
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
