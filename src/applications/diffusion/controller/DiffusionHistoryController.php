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
    $content = array();

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

    $history_panel = new PHUIObjectBoxView();
    $history_panel->setHeaderText(pht('History'));
    $history_panel->setTable($history_table);

    $content[] = $history_panel;

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($repository)
      ->setHeader($this->renderPathLinks($drequest, $mode = 'history'));

    $actions = $this->buildActionView($drequest);
    $properties = $this->buildPropertyView($drequest, $actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $crumbs = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'history',
      ));

    $pager_box = $this->renderTablePagerBox($pager);

    return $this->newPage()
      ->setTitle(
        array(
          pht('History'),
          $repository->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $object_box,
          $content,
          $pager_box,
        ));
  }

  private function buildActionView(DiffusionRequest $drequest) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $browse_uri = $drequest->generateURI(
      array(
        'action' => 'browse',
      ));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Browse Content'))
        ->setHref($browse_uri)
        ->setIcon('fa-files-o'));

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

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($branch_name)
        ->setIcon('fa-code-fork')
        ->setHref($branch_uri));

    return $view;
  }

  protected function buildPropertyView(
    DiffusionRequest $drequest,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setActionList($actions);

    $stable_commit = $drequest->getStableCommit();

    $view->addProperty(
      pht('Commit'),
      phutil_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action' => 'commit',
              'commit' => $stable_commit,
            )),
        ),
        $drequest->getRepository()->formatCommitName($stable_commit)));

    return $view;
  }

}
