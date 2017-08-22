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

    $history_list = id(new DiffusionHistoryListView())
      ->setViewer($viewer)
      ->setDiffusionRequest($drequest)
      ->setHistory($history);

    $history_list->loadRevisions();
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

    $no_path = !strlen($drequest->getPath());
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
