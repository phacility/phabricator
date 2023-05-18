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
    require_celerity_resource('diffusion-css');

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

    $history_results = $this->callConduitWithDiffusionRequest(
      'diffusion.historyquery',
      $params);
    $history = DiffusionPathChange::newFromConduit(
      $history_results['pathChanges']);

    $history = $pager->sliceResults($history);

    $history_list = id(new DiffusionCommitGraphView())
      ->setViewer($viewer)
      ->setDiffusionRequest($drequest)
      ->setHistory($history);

    // NOTE: If we have a path (like "src/"), many nodes in the graph are
    // likely to be missing (since the path wasn't touched by those commits).

    // If we draw the graph, commits will often appear to be unrelated because
    // intermediate nodes are omitted. Just drop the graph.

    // The ideal behavior would be to load the entire graph and then connect
    // ancestors appropriately, but this would currrently be prohibitively
    // expensive in the general case.

    $show_graph = ($drequest->getPath() === null
      || !strlen($drequest->getPath()));
    if ($show_graph) {
      $history_list
        ->setParents($history_results['parents'])
        ->setIsHead(!$pager->getOffset())
        ->setIsTail(!$pager->getHasMorePages());
    }

    $header = $this->buildHeader($drequest);

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'history',
      ));
    $crumbs->setBorder(true);

    $title = array(
      pht('History'),
      $repository->getDisplayName(),
    );

    $pager = id(new PHUIBoxView())
      ->addClass('mlb')
      ->appendChild($pager);

    $tabs = $this->buildTabsView('history');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setTabs($tabs)
      ->setFooter(array(
        $history_list,
        $pager,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view)
      ->addClass('diffusion-history-view');
  }

  private function buildHeader(DiffusionRequest $drequest) {
    $viewer = $this->getViewer();
    $repository = $drequest->getRepository();

    $no_path = $drequest->getPath() === null || !strlen($drequest->getPath());
    if ($no_path) {
      $header_text = pht('History');
    } else {
      $header_text = $this->renderPathLinks($drequest, $mode = 'history');
    }

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($header_text)
      ->setHeaderIcon('fa-clock-o');

    if (!$repository->isSVN()) {
      $branch_tag = $this->renderBranchTag($drequest);
      $header->addTag($branch_tag);
    }

    if ($drequest->getSymbolicCommit()) {
      $symbolic_tag = $this->renderSymbolicCommit($drequest);
      $header->addTag($symbolic_tag);
    }

    return $header;

  }

}
