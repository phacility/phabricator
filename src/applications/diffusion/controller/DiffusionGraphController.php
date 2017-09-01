<?php

final class DiffusionGraphController extends DiffusionController {

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

    $graph = id(new DiffusionHistoryTableView())
      ->setViewer($viewer)
      ->setDiffusionRequest($drequest)
      ->setHistory($history);

    $graph->loadRevisions();
    $show_graph = !strlen($drequest->getPath());
    if ($show_graph) {
      $graph->setParents($history_results['parents']);
      $graph->setIsHead(!$pager->getOffset());
      $graph->setIsTail(!$pager->getHasMorePages());
    }

    $header = $this->buildHeader($drequest);

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'graph',
      ));
    $crumbs->setBorder(true);

    $title = array(
      pht('Graph'),
      $repository->getDisplayName(),
    );

    $graph_view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('History Graph'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($graph)
      ->addClass('diffusion-mobile-view')
      ->setPager($pager);

    $tabs = $this->buildTabsView('graph');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setTabs($tabs)
      ->setFooter($graph_view);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildHeader(DiffusionRequest $drequest) {
    $viewer = $this->getViewer();
    $repository = $drequest->getRepository();

    $no_path = !strlen($drequest->getPath());
    if ($no_path) {
      $header_text = pht('Graph');
    } else {
      $header_text = $this->renderPathLinks($drequest, $mode = 'history');
    }

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($header_text)
      ->setHeaderIcon('fa-code-fork');

    if (!$repository->isSVN()) {
      $branch_tag = $this->renderBranchTag($drequest);
      $header->addTag($branch_tag);
    }

    return $header;

  }

}
