<?php

final class DiffusionHistoryTableView extends DiffusionView {

  private $history;
  private $revisions = array();
  private $handles = array();
  private $isHead;
  private $isTail;
  private $parents;
  private $filterParents;

  public function setHistory(array $history) {
    assert_instances_of($history, 'DiffusionPathChange');
    $this->history = $history;
    return $this;
  }

  public function loadRevisions() {
    $commit_phids = array();
    foreach ($this->history as $item) {
      if ($item->getCommit()) {
        $commit_phids[] = $item->getCommit()->getPHID();
      }
    }

    // TODO: Get rid of this.
    $this->revisions = id(new DifferentialRevision())
      ->loadIDsByCommitPHIDs($commit_phids);
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  private function getRequiredHandlePHIDs() {
    $phids = array();
    foreach ($this->history as $item) {
      $data = $item->getCommitData();
      if ($data) {
        if ($data->getCommitDetail('authorPHID')) {
          $phids[$data->getCommitDetail('authorPHID')] = true;
        }
        if ($data->getCommitDetail('committerPHID')) {
          $phids[$data->getCommitDetail('committerPHID')] = true;
        }
      }
    }
    return array_keys($phids);
  }

  public function setParents(array $parents) {
    $this->parents = $parents;
    return $this;
  }

  public function setIsHead($is_head) {
    $this->isHead = $is_head;
    return $this;
  }

  public function setIsTail($is_tail) {
    $this->isTail = $is_tail;
    return $this;
  }

  public function setFilterParents($filter_parents) {
    $this->filterParents = $filter_parents;
    return $this;
  }

  public function getFilterParents() {
    return $this->filterParents;
  }

  public function render() {
    $drequest = $this->getDiffusionRequest();

    $viewer = $this->getUser();

    $buildables = $this->loadBuildables(mpull($this->history, 'getCommit'));
    $has_any_build = false;

    $show_revisions = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDifferentialApplication',
      $viewer);

    $handles = $viewer->loadHandles($this->getRequiredHandlePHIDs());

    $graph = null;
    if ($this->parents) {
      $parents = $this->parents;

      // If we're filtering parents, remove relationships which point to
      // commits that are not part of the visible graph. Otherwise, we get
      // a big tree of nonsense when viewing release branches like "stable"
      // versus "master".
      if ($this->filterParents) {
        foreach ($parents as $key => $nodes) {
          foreach ($nodes as $nkey => $node) {
            if (empty($parents[$node])) {
              unset($parents[$key][$nkey]);
            }
          }
        }
      }

      $graph = id(new PHUIDiffGraphView())
        ->setIsHead($this->isHead)
        ->setIsTail($this->isTail)
        ->renderGraph($parents);
    }

    $show_builds = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorHarbormasterApplication',
      $this->getUser());

    $rows = array();
    $ii = 0;
    foreach ($this->history as $history) {
      $epoch = $history->getEpoch();

      if ($epoch) {
        $committed = $viewer->formatShortDateTime($epoch);
      } else {
        $committed = null;
      }

      $data = $history->getCommitData();
      $author_phid = $committer = $committer_phid = null;
      if ($data) {
        $author_phid = $data->getCommitDetail('authorPHID');
        $committer_phid = $data->getCommitDetail('committerPHID');
        $committer = $data->getCommitDetail('committer');
      }

      if ($author_phid && isset($handles[$author_phid])) {
        $author = $handles[$author_phid]->renderLink();
      } else {
        $author = self::renderName($history->getAuthorName());
      }

      $different_committer = false;
      if ($committer_phid) {
        $different_committer = ($committer_phid != $author_phid);
      } else if ($committer != '') {
        $different_committer = ($committer != $history->getAuthorName());
      }
      if ($different_committer) {
        if ($committer_phid && isset($handles[$committer_phid])) {
          $committer = $handles[$committer_phid]->renderLink();
        } else {
          $committer = self::renderName($committer);
        }
        $author = hsprintf('%s/%s', $author, $committer);
      }

      // We can show details once the message and change have been imported.
      $partial_import = PhabricatorRepositoryCommit::IMPORTED_MESSAGE |
                        PhabricatorRepositoryCommit::IMPORTED_CHANGE;

      $commit = $history->getCommit();
      if ($commit && $commit->isPartiallyImported($partial_import) && $data) {
        $summary = AphrontTableView::renderSingleDisplayLine(
          $history->getSummary());
      } else {
        $summary = phutil_tag('em', array(), pht("Importing\xE2\x80\xA6"));
      }

      $build = null;
      if ($show_builds) {
        $buildable = idx($buildables, $commit->getPHID());
        if ($buildable !== null) {
          $build = $this->renderBuildable($buildable);
          $has_any_build = true;
        }
      }

      $browse = $this->linkBrowse(
        $history->getPath(),
        array(
          'commit' => $history->getCommitIdentifier(),
          'branch' => $drequest->getBranch(),
          'type' => $history->getFileType(),
        ));

      $status = $commit->getAuditStatus();
      $icon = PhabricatorAuditCommitStatusConstants::getStatusIcon($status);
      $color = PhabricatorAuditCommitStatusConstants::getStatusColor($status);
      $name = PhabricatorAuditCommitStatusConstants::getStatusName($status);

      $audit_view = id(new PHUIIconView())
        ->setIcon($icon, $color)
        ->addSigil('has-tooltip')
        ->setMetadata(
          array(
            'tip' => $name,
          ));

      $rows[] = array(
        $graph ? $graph[$ii++] : null,
        $browse,
        self::linkCommit(
          $drequest->getRepository(),
          $history->getCommitIdentifier()),
        $build,
        $audit_view,
        ($commit ?
          self::linkRevision(idx($this->revisions, $commit->getPHID())) :
          null),
        $author,
        $summary,
        $committed,
      );
    }

    $view = new AphrontTableView($rows);
    $view->setHeaders(
      array(
        null,
        null,
        pht('Commit'),
        null,
        null,
        null,
        pht('Author'),
        pht('Details'),
        pht('Committed'),
      ));
    $view->setColumnClasses(
      array(
        'threads',
        'nudgeright',
        '',
        'icon',
        'icon',
        '',
        '',
        'wide',
        'right',
      ));
    $view->setColumnVisibility(
      array(
        $graph ? true : false,
        true,
        true,
        $has_any_build,
        true,
        $show_revisions,
      ));
    $view->setDeviceVisibility(
      array(
        $graph ? true : false,
        true,
        true,
        true,
        true,
        true,
        false,
        true,
        false,
      ));
    return $view->render();
  }

}
