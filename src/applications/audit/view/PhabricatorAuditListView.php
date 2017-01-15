<?php

final class PhabricatorAuditListView extends AphrontView {

  private $commits;
  private $header;
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

    $handles = $viewer->loadHandles(mpull($this->commits, 'getPHID'));

    $list = new PHUIObjectItemListView();
    foreach ($this->commits as $commit) {
      $commit_phid = $commit->getPHID();
      $commit_handle = $handles[$commit_phid];
      $committed = null;

      $commit_name = $commit_handle->getName();
      $commit_link = $commit_handle->getURI();
      $commit_desc = $this->getCommitDescription($commit_phid);
      $committed = phabricator_datetime($commit->getEpoch(), $viewer);

      $audits = mpull($commit->getAudits(), null, 'getAuditorPHID');
      $auditors = array();
      $reasons = array();
      foreach ($audits as $audit) {
        $auditor_phid = $audit->getAuditorPHID();
        $auditors[$auditor_phid] = $viewer->renderHandle($auditor_phid);
      }
      $auditors = phutil_implode_html(', ', $auditors);

      $status = $commit->getAuditStatus();

      $status_text =
        PhabricatorAuditCommitStatusConstants::getStatusName($status);
      $status_color =
        PhabricatorAuditCommitStatusConstants::getStatusColor($status);
      $status_icon =
        PhabricatorAuditCommitStatusConstants::getStatusIcon($status);

      $author_phid = $commit->getAuthorPHID();
      if ($author_phid) {
        $author_name = $viewer->renderHandle($author_phid);
      } else {
        $author_name = $commit->getCommitData()->getAuthorName();
      }

      $item = id(new PHUIObjectItemView())
        ->setObjectName($commit_name)
        ->setHeader($commit_desc)
        ->setHref($commit_link)
        ->addAttribute(pht('Author: %s', $author_name))
        ->addAttribute($reasons)
        ->addIcon('none', $committed);

      if (!empty($auditors)) {
        $item->addByLine(pht('Auditors: %s', $auditors));
      }

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
