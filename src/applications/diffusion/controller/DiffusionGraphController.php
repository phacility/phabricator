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
      ->setPager($pager);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($graph_view);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildHeader(DiffusionRequest $drequest) {
    $viewer = $this->getViewer();

    $tag = $this->renderCommitHashTag($drequest);
    $history_uri = $drequest->generateURI(
      array(
        'action' => 'history',
      ));

    $history_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('History'))
      ->setHref($history_uri)
      ->setIcon('fa-history');

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($drequest->getRepository())
      ->addTag($tag)
      ->setHeader($this->renderPathLinks($drequest, $mode = 'history'))
      ->setHeaderIcon('fa-code-fork')
      ->addActionLink($history_button);

    return $header;

  }

}
