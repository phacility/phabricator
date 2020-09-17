<?php

final class DiffusionCommitGraphView
  extends DiffusionView {

  private $history;
  private $commits;
  private $isHead;
  private $isTail;
  private $parents;
  private $filterParents;

  private $commitMap;
  private $buildableMap;
  private $revisionMap;

  private $showAuditors;

  public function setHistory(array $history) {
    assert_instances_of($history, 'DiffusionPathChange');
    $this->history = $history;
    return $this;
  }

  public function getHistory() {
    return $this->history;
  }

  public function setCommits(array $commits) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');
    $this->commits = $commits;
    return $this;
  }

  public function getCommits() {
    return $this->commits;
  }

  public function setShowAuditors($show_auditors) {
    $this->showAuditors = $show_auditors;
    return $this;
  }

  public function getShowAuditors() {
    return $this->showAuditors;
  }

  public function setParents(array $parents) {
    $this->parents = $parents;
    return $this;
  }

  public function getParents() {
    return $this->parents;
  }

  public function setIsHead($is_head) {
    $this->isHead = $is_head;
    return $this;
  }

  public function getIsHead() {
    return $this->isHead;
  }

  public function setIsTail($is_tail) {
    $this->isTail = $is_tail;
    return $this;
  }

  public function getIsTail() {
    return $this->isTail;
  }

  public function setFilterParents($filter_parents) {
    $this->filterParents = $filter_parents;
    return $this;
  }

  public function getFilterParents() {
    return $this->filterParents;
  }

  private function getRepository() {
    $drequest = $this->getDiffusionRequest();

    if (!$drequest) {
      return null;
    }

    return $drequest->getRepository();
  }

  public function newObjectItemListView() {
    $list_view = id(new PHUIObjectItemListView());

    $item_views = $this->newObjectItemViews();
    foreach ($item_views as $item_view) {
      $list_view->addItem($item_view);
    }

    return $list_view;
  }

  private function newObjectItemViews() {
    $viewer = $this->getViewer();

    require_celerity_resource('diffusion-css');

    $show_builds = $this->shouldShowBuilds();
    $show_revisions = $this->shouldShowRevisions();
    $show_auditors = $this->shouldShowAuditors();

    $phids = array();

    if ($show_revisions) {
      $revision_map = $this->getRevisionMap();
      foreach ($revision_map as $revisions) {
        foreach ($revisions as $revision) {
          $phids[] = $revision->getPHID();
        }
      }
    }

    $commits = $this->getCommitMap();

    foreach ($commits as $commit) {
      $author_phid = $commit->getAuthorDisplayPHID();
      if ($author_phid !== null) {
        $phids[] = $author_phid;
      }
    }

    if ($show_auditors) {
      foreach ($commits as $commit) {
        $audits = $commit->getAudits();
        foreach ($audits as $auditor) {
          $phids[] = $auditor->getAuditorPHID();
        }
      }
    }

    $handles = $viewer->loadHandles($phids);

    $views = array();

    $items = $this->newHistoryItems();
    foreach ($items as $hash => $item) {
      $content = array();

      $commit = $item['commit'];

      $commit_description = $this->getCommitDescription($commit);
      $commit_link = $this->getCommitURI($hash);

      $short_hash = $this->getCommitObjectName($hash);
      $is_disabled = $this->getCommitIsDisabled($commit);

      $item_view = id(new PHUIObjectItemView())
        ->setViewer($viewer)
        ->setHeader($commit_description)
        ->setObjectName($short_hash)
        ->setHref($commit_link)
        ->setDisabled($is_disabled);

      $this->addBrowseAction($item_view, $hash);

      if ($show_builds) {
        $this->addBuildAction($item_view, $hash);
      }

      $this->addAuditAction($item_view, $hash);

      if ($show_auditors) {
        $auditor_list = $item_view->newMapView();
        if ($commit) {
          $auditors = $this->newAuditorList($commit, $handles);
          $auditor_list->newItem()
            ->setName(pht('Auditors'))
            ->setValue($auditors);
        }
      }

      $property_list = $item_view->newMapView();

      if ($commit) {
        $author_view = $this->getCommitAuthorView($commit);
        if ($author_view) {
          $property_list->newItem()
            ->setName(pht('Author'))
            ->setValue($author_view);
        }
      }

      if ($show_revisions) {
        if ($commit) {
          $revisions = $this->getRevisions($commit);
          if ($revisions) {
            $list_view = $handles->newSublist(mpull($revisions, 'getPHID'))
              ->newListView();

            $property_list->newItem()
              ->setName(pht('Revisions'))
              ->setValue($list_view);
          }
        }
      }

      $views[$hash] = $item_view;
    }

    return $views;
  }

  private function newObjectItemRows() {
    $viewer = $this->getViewer();

    $items = $this->newHistoryItems();
    $views = $this->newObjectItemViews();

    $last_date = null;
    $rows = array();
    foreach ($items as $hash => $item) {
      $item_epoch = $item['epoch'];
      $item_date = phabricator_date($item_epoch, $viewer);
      if ($item_date !== $last_date) {
        $last_date = $item_date;
        $header = $item_date;
      } else {
        $header = null;
      }

      $item_view = $views[$hash];

      $list_view = id(new PHUIObjectItemListView())
        ->setViewer($viewer)
        ->setFlush(true)
        ->addItem($item_view);

      if ($header !== null) {
        $list_view->setHeader($header);
      }

      $rows[] = $list_view;
    }

    return $rows;
  }

  public function render() {
    $rows = $this->newObjectItemRows();

    $graph = $this->newGraphView();
    foreach ($rows as $idx => $row) {
      $cells = array();

      if ($graph) {
        $cells[] = phutil_tag(
          'td',
          array(
            'class' => 'diffusion-commit-graph-path-cell',
          ),
          $graph[$idx]);
      }

      $cells[] = phutil_tag(
        'td',
        array(
          'class' => 'diffusion-commit-graph-content-cell',
        ),
        $row);

      $rows[$idx] = phutil_tag('tr', array(), $cells);
    }

    $table = phutil_tag(
      'table',
      array(
        'class' => 'diffusion-commit-graph-table',
      ),
      $rows);

    return $table;
  }

  private function newGraphView() {
    if (!$this->getParents()) {
      return null;
    }

    $parents = $this->getParents();

    // If we're filtering parents, remove relationships which point to
    // commits that are not part of the visible graph. Otherwise, we get
    // a big tree of nonsense when viewing release branches like "stable"
    // versus "master".
    if ($this->getFilterParents()) {
      foreach ($parents as $key => $nodes) {
        foreach ($nodes as $nkey => $node) {
          if (empty($parents[$node])) {
            unset($parents[$key][$nkey]);
          }
        }
      }
    }

    return id(new PHUIDiffGraphView())
      ->setIsHead($this->getIsHead())
      ->setIsTail($this->getIsTail())
      ->renderGraph($parents);
  }

  private function shouldShowBuilds() {
    $viewer = $this->getViewer();

    $show_builds = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorHarbormasterApplication',
      $this->getUser());

    return $show_builds;
  }

  private function shouldShowRevisions() {
    $viewer = $this->getViewer();

    $show_revisions = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDifferentialApplication',
      $viewer);

    return $show_revisions;
  }

  private function shouldShowAuditors() {
    return $this->getShowAuditors();
  }

  private function newHistoryItems() {
    $items = array();

    $history = $this->getHistory();
    if ($history !== null) {
      foreach ($history as $history_item) {
        $commit_hash = $history_item->getCommitIdentifier();

        $items[$commit_hash] = array(
          'epoch' => $history_item->getEpoch(),
          'hash' => $commit_hash,
          'commit' => $this->getCommit($commit_hash),
        );
      }
    } else {
      $commits = $this->getCommitMap();
      foreach ($commits as $commit) {
        $commit_hash = $commit->getCommitIdentifier();

        $items[$commit_hash] = array(
          'epoch' => $commit->getEpoch(),
          'hash' => $commit_hash,
          'commit' => $commit,
        );
      }
    }

    return $items;
  }

  private function getCommitDescription($commit) {
    if (!$commit) {
      return phutil_tag('em', array(), pht("Discovering\xE2\x80\xA6"));
    }

    // We can show details once the message and change have been imported.
    $partial_import = PhabricatorRepositoryCommit::IMPORTED_MESSAGE |
                      PhabricatorRepositoryCommit::IMPORTED_CHANGE;
    if (!$commit->isPartiallyImported($partial_import)) {
      return phutil_tag('em', array(), pht("Importing\xE2\x80\xA6"));
    }

    return $commit->getCommitData()->getSummary();
  }

  private function getCommitURI($hash) {
    $repository = $this->getRepository();

    if ($repository) {
      return $repository->getCommitURI($hash);
    }

    $commit = $this->getCommit($hash);
    if ($commit) {
      return $commit->getURI();
    }

    return null;
  }

  private function getCommitObjectName($hash) {
    $repository = $this->getRepository();

    if ($repository) {
      return $repository->formatCommitName(
        $hash,
        $is_local = true);
    }

    $commit = $this->getCommit($hash);
    if ($commit) {
      return $commit->getDisplayName();
    }

    return null;
  }

  private function getCommitIsDisabled($commit) {
    if (!$commit) {
      return true;
    }

    if ($commit->isUnreachable()) {
      return true;
    }

    return false;
  }

  private function getCommitAuthorView($commit) {
    if (!$commit) {
      return null;
    }

    $viewer = $this->getViewer();

    $author_phid = $commit->getAuthorDisplayPHID();
    if ($author_phid) {
      return $viewer->loadHandles(array($author_phid))
        ->newListView();
    }

    return $commit->newCommitAuthorView($viewer);
  }

  private function getCommit($hash) {
    $commit_map = $this->getCommitMap();
    return idx($commit_map, $hash);
  }

  private function getCommitMap() {
    if ($this->commitMap === null) {
      $commit_list = $this->newCommitList();
      $this->commitMap = mpull($commit_list, null, 'getCommitIdentifier');
    }

    return $this->commitMap;
  }

  private function addBrowseAction(PHUIObjectItemView $item, $hash) {
    $repository = $this->getRepository();

    if (!$repository) {
      return;
    }

    $drequest = $this->getDiffusionRequest();
    $path = $drequest->getPath();

    $uri = $drequest->generateURI(
      array(
        'action' => 'browse',
        'path' => $path,
        'commit' => $hash,
      ));

    $menu_item = $item->newMenuItem()
      ->setName(pht('Browse Repository'))
      ->setURI($uri);

    $menu_item->newIcon()
      ->setIcon('fa-folder-open-o')
      ->setColor('bluegrey');
  }

  private function addBuildAction(PHUIObjectItemView $item, $hash) {
    $is_disabled = true;

    $buildable = null;

    $commit = $this->getCommit($hash);
    if ($commit) {
      $buildable = $this->getBuildable($commit);
    }

    if ($buildable) {
      $icon = $buildable->getStatusIcon();
      $color = $buildable->getStatusColor();
      $name = $buildable->getStatusDisplayName();
      $uri = $buildable->getURI();
    } else {
      $icon = 'fa-times';
      $color = 'grey';
      $name = pht('No Builds');
      $uri = null;
    }

    $menu_item = $item->newMenuItem()
      ->setName($name)
      ->setURI($uri)
      ->setDisabled(($uri === null));

    $menu_item->newIcon()
      ->setIcon($icon)
      ->setColor($color);
  }

  private function addAuditAction(PHUIObjectItemView $item_view, $hash) {
    $commit = $this->getCommit($hash);

    if ($commit) {
      $status = $commit->getAuditStatusObject();

      $text = $status->getName();
      $icon = $status->getIcon();

      $is_disabled = $status->isNoAudit();
      if ($is_disabled) {
        $uri = null;
        $color = 'grey';
      } else {
        $color = $status->getColor();
        $uri = $commit->getURI();
      }
    } else {
      $text = pht('No Audit');
      $color = 'grey';
      $icon = 'fa-times';
      $uri = null;

      $is_disabled = true;
    }

    $menu_item = $item_view->newMenuItem()
      ->setName($text)
      ->setURI($uri)
      ->setBackgroundColor($color)
      ->setDisabled($is_disabled);

    $menu_item->newIcon()
      ->setIcon($icon)
      ->setColor($color);
  }

  private function getBuildable(PhabricatorRepositoryCommit $commit) {
    $buildable_map = $this->getBuildableMap();
    return idx($buildable_map, $commit->getPHID());
  }

  private function getBuildableMap() {
    if ($this->buildableMap === null) {
      $commits = $this->getCommitMap();
      $buildables = $this->loadBuildables($commits);
      $this->buildableMap = $buildables;
    }

    return $this->buildableMap;
  }

  private function getRevisions(PhabricatorRepositoryCommit $commit) {
    $revision_map = $this->getRevisionMap();
    return idx($revision_map, $commit->getPHID(), array());
  }

  private function getRevisionMap() {
    if ($this->revisionMap === null) {
      $this->revisionMap = $this->newRevisionMap();
    }

    return $this->revisionMap;
  }

  private function newRevisionMap() {
    $viewer = $this->getViewer();
    $commits = $this->getCommitMap();

    return DiffusionCommitRevisionQuery::loadRevisionMapForCommits(
      $viewer,
      $commits);
  }

  private function newCommitList() {
    $commits = $this->getCommits();
    if ($commits !== null) {
      return $commits;
    }

    $repository = $this->getRepository();
    if (!$repository) {
      return array();
    }

    $history = $this->getHistory();
    if ($history === null) {
      return array();
    }

    $identifiers = array();
    foreach ($history as $item) {
      $identifiers[] = $item->getCommitIdentifier();
    }

    if (!$identifiers) {
      return array();
    }

    $viewer = $this->getViewer();

    $commits = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withRepository($repository)
      ->withIdentifiers($identifiers)
      ->needCommitData(true)
      ->needIdentities(true)
      ->execute();

    return $commits;
  }

  private function newAuditorList(
    PhabricatorRepositoryCommit $commit,
    $handles) {

    $auditors = $commit->getAudits();
    if (!$auditors) {
      return phutil_tag('em', array(), pht('None'));
    }

    $auditor_phids = mpull($auditors, 'getAuditorPHID');
    $auditor_list = $handles->newSublist($auditor_phids)
      ->newListView();

    return $auditor_list;
  }

}
