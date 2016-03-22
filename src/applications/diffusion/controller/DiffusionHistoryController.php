<?php

final class DiffusionHistoryController extends DiffusionController {

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
      'commit' => $drequest->getCommit(),
      'path' => $drequest->getPath(),
      'offset' => $pager->getOffset(),
      'limit' => $pager->getPageSize() + 1,
    );

    if (!$request->getBool('copies')) {
      $params['needDirectChanges'] = true;
      $params['needChildChanges'] = true;
    }

    $history_results = $this->callConduitWithDiffusionRequest(
      'diffusion.historyquery',
      $params);
    $history = DiffusionPathChange::newFromConduit(
      $history_results['pathChanges']);

    $history = $pager->sliceResults($history);

    $show_graph = !strlen($drequest->getPath());
    $history_table = id(new DiffusionHistoryTableView())
      ->setUser($request->getUser())
      ->setDiffusionRequest($drequest)
      ->setHistory($history);

    $history_table->loadRevisions();

    if ($show_graph) {
      $history_table->setParents($history_results['parents']);
      $history_table->setIsHead(!$pager->getOffset());
      $history_table->setIsTail(!$pager->getHasMorePages());
    }

    $history_header = $this->buildHistoryHeader($drequest);
    $history_panel = id(new PHUIObjectBoxView())
      ->setHeader($history_header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($history_table);

    $header = $this->buildHeader($drequest, $repository);

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'history',
      ));
    $crumbs->setBorder(true);

    $pager_box = $this->renderTablePagerBox($pager);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $history_panel,
        $pager_box,
      ));

    return $this->newPage()
      ->setTitle(
        array(
          pht('History'),
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
        ));
  }

  private function buildHeader(DiffusionRequest $drequest) {
    $viewer = $this->getViewer();

    $tag = $this->renderCommitHashTag($drequest);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($drequest->getRepository())
      ->addTag($tag)
      ->setHeader($this->renderPathLinks($drequest, $mode = 'history'))
      ->setHeaderIcon('fa-clock-o');

    return $header;

  }

  private function buildHistoryHeader(DiffusionRequest $drequest) {
    $viewer = $this->getViewer();

    $browse_uri = $drequest->generateURI(
      array(
        'action' => 'browse',
      ));

    $browse_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Browse'))
      ->setHref($browse_uri)
      ->setIcon('fa-files-o');

    // TODO: Sometimes we do have a change view, we need to look at the most
    // recent history entry to figure it out.

    $request = $this->getRequest();
    if ($request->getBool('copies')) {
      $branch_name = pht('Hide Copies/Branches');
      $branch_uri = $request->getRequestURI()
        ->alter('offset', null)
        ->alter('copies', null);
    } else {
      $branch_name = pht('Show Copies/Branches');
      $branch_uri = $request->getRequestURI()
        ->alter('offset', null)
        ->alter('copies', true);
    }

    $branch_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText($branch_name)
      ->setIcon('fa-code-fork')
      ->setHref($branch_uri);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('History'))
      ->addActionLink($browse_button)
      ->addActionLink($branch_button);

    return $header;
  }

}
