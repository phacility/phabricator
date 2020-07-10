<?php

final class DiffusionCommitGraphView
  extends DiffusionView {

  private $history;
  private $commits = array();
  private $isHead;
  private $isTail;
  private $parents;
  private $filterParents;

  private $commitMap = array();
  private $buildableMap;
  private $revisionMap;

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
    $this->commitMap = mpull($commits, null, 'getCommitIdentifier');
    return $this;
  }

  public function getCommits() {
    return $this->commits;
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

  public function render() {
    $viewer = $this->getViewer();

    require_celerity_resource('diffusion-css');

    $show_builds = $this->shouldShowBuilds();
    $show_revisions = $this->shouldShowRevisions();

    $items = $this->newHistoryItems();

    $rows = array();
    $last_date = null;
    foreach ($items as $item) {
      $content = array();

      $item_epoch = $item['epoch'];
      $item_hash = $item['hash'];
      $commit = $item['commit'];

      $item_date = phabricator_date($item_epoch, $viewer);
      if ($item_date !== $last_date) {
        $last_date = $item_date;
        $content[] = phutil_tag(
          'div',
          array(
            'class' => 'diffusion-commit-graph-date-header',
          ),
          $item_date);
      }

      $commit_description = $this->getCommitDescription($commit);
      $commit_link = $this->getCommitURI($item_hash);

      $short_hash = $this->getCommitObjectName($item_hash);
      $is_disabled = $this->getCommitIsDisabled($commit);

      $author_view = $this->getCommitAuthorView($commit);

      $item_view = id(new PHUIObjectItemView())
        ->setHeader($commit_description)
        ->setObjectName($short_hash)
        ->setHref($commit_link)
        ->setDisabled($is_disabled);

      if ($author_view !== null) {
        $item_view->addAttribute($author_view);
      }

      $browse_button = $this->newBrowseButton($item_hash);

      $build_view = null;
      if ($show_builds) {
        $build_view = $this->newBuildView($item_hash);
      }

      $item_view->setSideColumn(
        array(
          $build_view,
          $browse_button,
        ));

      $revision_view = null;
      if ($show_revisions) {
        $revision_view = $this->newRevisionView($item_hash);
      }

      if ($revision_view !== null) {
        $item_view->addAttribute($revision_view);
      }

      $view = id(new PHUIObjectItemListView())
        ->setFlush(true)
        ->addItem($item_view);

      $content[] = $view;

      $rows[] = $content;
    }

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

  private function newHistoryItems() {
    $items = array();

    $commits = $this->getCommits();
    $commit_map = mpull($commits, null, 'getCommitIdentifier');

    $history = $this->getHistory();
    if ($history !== null) {
      foreach ($history as $history_item) {
        $commit_hash = $history_item->getCommitIdentifier();

        $items[] = array(
          'epoch' => $history_item->getEpoch(),
          'hash' => $commit_hash,
          'commit' => idx($commit_map, $commit_hash),
        );
      }
    } else {
      foreach ($commits as $commit) {
        $items[] = array(
          'epoch' => $commit->getEpoch(),
          'hash' => $commit->getCommitIdentifier(),
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
    return $commit->getURI();
  }

  private function getCommitObjectName($hash) {
    $repository = $this->getRepository();

    if ($repository) {
      return $repository->formatCommitName(
        $hash,
        $is_local = true);
    }

    $commit = $this->getCommit($hash);
    return $commit->getDisplayName();
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

    return $commit->newCommitAuthorView($viewer);
  }

  private function newBrowseButton($hash) {
    $commit = $this->getCommit($hash);

    return $this->linkBrowse(
      '/',
      array(
        'commit' => $hash,
      ),
      $as_button = true);
  }

  private function getCommit($hash) {
    $commit_map = $this->getCommitMap();
    return idx($commit_map, $hash);
  }

  private function getCommitMap() {
    return $this->commitMap;
  }

  private function newBuildView($hash) {
    $commit = $this->getCommit($hash);
    if (!$commit) {
      return null;
    }

    $buildable = $this->getBuildable($commit);
    if (!$buildable) {
      return null;
    }

    return $this->renderBuildable($buildable, 'button');
  }

  private function getBuildable(PhabricatorRepositoryCommit $commit) {
    $buildable_map = $this->getBuildableMap();
    return idx($buildable_map, $commit->getPHID());
  }

  private function getBuildableMap() {
    if ($this->buildableMap === null) {
      $commits = $this->getCommits();
      $buildables = $this->loadBuildables($commits);
      $this->buildableMap = $buildables;
    }

    return $this->buildableMap;
  }

  private function newRevisionView($hash) {
    $commit = $this->getCommit($hash);
    if (!$commit) {
      return null;
    }

    $revisions = $this->getRevisions($commit);
    if (!$revisions) {
      return null;
    }

    $revision = head($revisions);

    return id(new PHUITagView())
      ->setName($revision->getMonogram())
      ->setType(PHUITagView::TYPE_SHADE)
      ->setColor(PHUITagView::COLOR_BLUE)
      ->setHref($revision->getURI())
      ->setBorder(PHUITagView::BORDER_NONE)
      ->setSlimShady(true);
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
    $commits = $this->getCommits();

    return DiffusionCommitRevisionQuery::loadRevisionMapForCommits(
      $viewer,
      $commits);
  }

}
