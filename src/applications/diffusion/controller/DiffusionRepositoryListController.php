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

    $list = new PHUIObjectItemListView();
    foreach ($repositories as $repository) {
      $id = $repository->getID();

      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setHeader($repository->getName())
        ->setHref($this->getApplicationURI($repository->getCallsign().'/'));

      $commit = $repository->getMostRecentCommit();
      if ($commit) {
        $commit_link = DiffusionView::linkCommit(
            $repository,
            $commit->getCommitIdentifier(),
            $commit->getSummary());
        $item->setSubhead($commit_link);
        $item->setEpoch($commit->getEpoch());
      }

      $item->addAttribute(
        PhabricatorRepositoryType::getNameForRepositoryType(
          $repository->getVersionControlSystem()));

      $size = $repository->getCommitCount();
      if ($size) {
        $history_uri = DiffusionRequest::generateDiffusionURI(
          array(
            'callsign' => $repository->getCallsign(),
            'action' => 'history',
          ));

        $item->addAttribute(
          phutil_tag(
            'a',
            array(
              'href' => $history_uri,
            ),
            pht('%s Commit(s)', new PhutilNumber($size))));
      } else {
        $item->addAttribute(pht('No Commits'));
      }

      if (!$repository->isTracked()) {
        $item->setDisabled(true);
        $item->addIcon('disable-grey', pht('Inactive'));
      }

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
