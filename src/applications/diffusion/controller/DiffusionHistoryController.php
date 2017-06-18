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

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
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

    $tag = $this->renderCommitHashTag($drequest);
    $browse_uri = $drequest->generateURI(
      array(
        'action' => 'browse',
      ));

    $browse_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Browse'))
      ->setHref($browse_uri)
      ->setIcon('fa-code');

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($drequest->getRepository())
      ->addTag($tag)
      ->setHeader($this->renderPathLinks($drequest, $mode = 'history'))
      ->setHeaderIcon('fa-clock-o')
      ->addActionLink($browse_button);

    return $header;

  }

}
