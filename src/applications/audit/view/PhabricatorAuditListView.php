<?php

final class PhabricatorAuditListView extends AphrontView {

  private $commits;
  private $header;
  private $showDrafts;
  private $noDataString;
  private $highlightedAudits;

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function getNoDataString() {
    return $this->noDataString;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function getHeader() {
    return $this->header;
  }

  public function setShowDrafts($show_drafts) {
    $this->showDrafts = $show_drafts;
    return $this;
  }

  public function getShowDrafts() {
    return $this->showDrafts;
  }

  /**
   * These commits should have both commit data and audit requests attached.
   */
  public function setCommits(array $commits) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');
    $this->commits = mpull($commits, null, 'getPHID');
    return $this;
  }

  public function getCommits() {
    return $this->commits;
  }

  private function getCommitDescription($phid) {
    if ($this->commits === null) {
      return pht('(Unknown Commit)');
    }

    $commit = idx($this->commits, $phid);
    if (!$commit) {
      return pht('(Unknown Commit)');
    }

    $summary = $commit->getCommitData()->getSummary();
    if (strlen($summary)) {
      return $summary;
    }

    // No summary, so either this is still impoting or just has an empty
    // commit message.

    if (!$commit->isImported()) {
      return pht('(Importing Commit...)');
    } else {
      return pht('(Untitled Commit)');
    }
  }

  public function render() {
    $list = $this->buildList();
    $list->setFlush(true);
    return $list->render();
  }

  public function buildList() {
    $viewer = $this->getViewer();
    $rowc = array();

    $phids = array();
    foreach ($this->getCommits() as $commit) {
      $phids[] = $commit->getPHID();

      foreach ($commit->getAudits() as $audit) {
        $phids[] = $audit->getAuditorPHID();
      }

      $author_phid = $commit->getAuthorPHID();
      if ($author_phid) {
        $phids[] = $author_phid;
      }
    }

    $handles = $viewer->loadHandles($phids);

    $show_drafts = $this->getShowDrafts();

    $draft_icon = id(new PHUIIconView())
      ->setIcon('fa-comment yellow')
      ->addSigil('has-tooltip')
      ->setMetadata(
        array(
          'tip' => pht('Unsubmitted Comments'),
        ));

    $list = new PHUIObjectItemListView();
    foreach ($this->commits as $commit) {
      $commit_phid = $commit->getPHID();
      $commit_handle = $handles[$commit_phid];
      $committed = null;

      $commit_name = $commit_handle->getName();
      $commit_link = $commit_handle->getURI();
      $commit_desc = $this->getCommitDescription($commit_phid);
      $committed = phabricator_datetime($commit->getEpoch(), $viewer);

      $status = $commit->getAuditStatus();

      $status_text =
        PhabricatorAuditCommitStatusConstants::getStatusName($status);
      $status_color =
        PhabricatorAuditCommitStatusConstants::getStatusColor($status);
      $status_icon =
        PhabricatorAuditCommitStatusConstants::getStatusIcon($status);

      $author_phid = $commit->getAuthorPHID();
      if ($author_phid) {
        $author_name = $handles[$author_phid]->renderLink();
      } else {
        $author_name = $commit->getCommitData()->getAuthorName();
      }

      $item = id(new PHUIObjectItemView())
        ->setObjectName($commit_name)
        ->setHeader($commit_desc)
        ->setHref($commit_link)
        ->setDisabled($commit->isUnreachable())
        ->addByline(pht('Author: %s', $author_name))
        ->addIcon('none', $committed);

      if ($show_drafts) {
        if ($commit->getHasDraft($viewer)) {
          $item->addAttribute($draft_icon);
        }
      }

      $audits = $commit->getAudits();
      $auditor_phids = mpull($audits, 'getAuditorPHID');
      if ($auditor_phids) {
        $auditor_list = $handles->newSublist($auditor_phids)
          ->renderList()
          ->setAsInline(true);
      } else {
        $auditor_list = phutil_tag('em', array(), pht('None'));
      }
      $item->addAttribute(pht('Auditors: %s', $auditor_list));

      if ($status_color) {
        $item->setStatusIcon($status_icon.' '.$status_color, $status_text);
      }

      $list->addItem($item);
    }

    if ($this->noDataString) {
      $list->setNoDataString($this->noDataString);
    }

    if ($this->header) {
      $list->setHeader($this->header);
    }

    return $list;
  }

}
