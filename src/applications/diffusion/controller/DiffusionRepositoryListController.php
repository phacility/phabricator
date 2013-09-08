<?php

final class DiffusionRepositoryListController extends DiffusionController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setPreface($this->buildShortcuts())
      ->setSearchEngine(new PhabricatorRepositorySearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $repositories,
    PhabricatorSavedQuery $query) {
    assert_instances_of($repositories, 'PhabricatorRepository');

    $viewer = $this->getRequest()->getUser();

    $rows = array();
    foreach ($repositories as $repository) {
      $id = $repository->getID();

      $size = $repository->getCommitCount();
      if ($size) {
        $size = hsprintf(
          '<a href="%s">%s</a>',
          DiffusionRequest::generateDiffusionURI(array(
            'callsign' => $repository->getCallsign(),
            'action' => 'history',
          )),
          pht('%s Commits', new PhutilNumber($size)));
      }

      $datetime = '';
      $most_recent_commit = $repository->getMostRecentCommit();
      if ($most_recent_commit) {
        $date = phabricator_date($most_recent_commit->getEpoch(), $viewer);
        $time = phabricator_time($most_recent_commit->getEpoch(), $viewer);
        $datetime = $date.' '.$time;
      }

      $rows[] = array(
        $repository->getName(),
        ('/diffusion/'.$repository->getCallsign().'/'),
        PhabricatorRepositoryType::getNameForRepositoryType(
          $repository->getVersionControlSystem()),
        $size ? $size : null,
        $most_recent_commit
          ? DiffusionView::linkCommit(
              $repository,
              $most_recent_commit->getCommitIdentifier(),
              $most_recent_commit->getSummary())
          : pht('No Commits'),
        $datetime
      );
    }

    $repository_tool_uri = PhabricatorEnv::getProductionURI('/repository/');
    $repository_tool     = phutil_tag('a',
      array(
       'href' => $repository_tool_uri,
      ),
      'repository tool');
    $preface = pht('This instance of Phabricator does not have any '.
                   'configured repositories.');
    if ($viewer->getIsAdmin()) {
      $no_repositories_txt = hsprintf(
        '%s %s',
        $preface,
        pht(
          'To setup one or more repositories, visit the %s.',
          $repository_tool));
    } else {
      $no_repositories_txt = hsprintf(
        '%s %s',
        $preface,
        pht(
          'Ask an administrator to setup one or more repositories '.
          'via the %s.',
          $repository_tool));
    }

    $list = new PHUIObjectItemListView();
    foreach ($rows as $row) {
      $item = id(new PHUIObjectItemView())
        ->setHeader($row[0])
        ->setSubHead($row[4])
        ->setHref($row[1])
        ->addAttribute(($row[2] ? $row[2] : pht('No Information')))
        ->addAttribute(($row[3] ? $row[3] : pht('0 Commits')))
        ->addIcon('none', $row[5]);
      $list->addItem($item);
    }

    return $list;
  }

  public function buildSideNavView($for_app = false) {
    $viewer = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));


    id(new PhabricatorRepositorySearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  private function buildShortcuts() {
    $shortcuts = id(new PhabricatorRepositoryShortcut())->loadAll();
    if ($shortcuts) {
      $shortcuts = msort($shortcuts, 'getSequence');

      $rows = array();
      foreach ($shortcuts as $shortcut) {
        $rows[] = array(
          $shortcut->getName(),
          $shortcut->getHref(),
          $shortcut->getDescription(),
        );
      }

      $list = new PHUIObjectItemListView();
      foreach ($rows as $row) {
        $item = id(new PHUIObjectItemView())
          ->setHeader($row[0])
          ->setHref($row[1])
          ->setSubhead(($row[2] ? $row[2] : pht('No Description')));
        $list->addItem($item);
      }
      $shortcut_panel = array($list, phutil_tag('hr'));
    } else {
      $shortcut_panel = null;
    }
    return $shortcut_panel;
  }

}
