<?php

final class DiffusionTagListController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }
    require_celerity_resource('diffusion-css');

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $pager = id(new PHUIPagerView())
      ->readFromRequest($request);

    $params = array(
      'limit' => $pager->getPageSize() + 1,
      'offset' => $pager->getOffset(),
    );

    if (strlen($drequest->getSymbolicCommit())) {
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

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Tags'))
      ->setHeaderIcon('fa-tags');

    if (!$repository->isSVN()) {
      $branch_tag = $this->renderBranchTag($drequest);
      $header->addTag($branch_tag);
    }

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

      $tag_list = id(new DiffusionTagListView())
        ->setTags($tags)
        ->setUser($viewer)
        ->setCommits($commits)
        ->setDiffusionRequest($drequest);

      $phids = $tag_list->getRequiredHandlePHIDs();
      $handles = $this->loadViewerHandles($phids);
      $tag_list->setHandles($handles);

      $content = id(new PHUIObjectBoxView())
        ->setHeaderText($repository->getDisplayName())
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->setTable($tag_list)
        ->setPager($pager);
    }

    $crumbs = $this->buildCrumbs(
      array(
        'tags' => true,
        'commit' => $drequest->getSymbolicCommit(),
      ));
    $crumbs->setBorder(true);

    $tabs = $this->buildTabsView('tags');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setTabs($tabs)
      ->setFooter($content);

    return $this->newPage()
      ->setTitle(
        array(
          pht('Tags'),
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild($view)
      ->addClass('diffusion-history-view');
  }

}
