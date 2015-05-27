<?php

final class PhabricatorAuditListView extends AphrontView {

  private $commits;
  private $handles;
  private $authorityPHIDs = array();
  private $noDataString;

  private $highlightedAudits;

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setAuthorityPHIDs(array $phids) {
    $this->authorityPHIDs = $phids;
    return $this;
  }

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function getNoDataString() {
    return $this->noDataString;
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

  public function getRequiredHandlePHIDs() {
    $phids = array();
    $commits = $this->getCommits();
    foreach ($commits as $commit) {
      $phids[$commit->getPHID()] = true;
      $phids[$commit->getAuthorPHID()] = true;
      $audits = $commit->getAudits();
      foreach ($audits as $audit) {
        $phids[$audit->getAuditorPHID()] = true;
      }
    }
    return array_keys($phids);
  }

  private function getHandle($phid) {
    $handle = idx($this->handles, $phid);
    if (!$handle) {
      throw new Exception(pht("No handle for '%s'!", $phid));
    }
    return $handle;
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
    $user = $this->getUser();
    if (!$user) {
      throw new Exception(
        pht(
          'You must %s before %s!',
          'setUser()',
          __FUNCTION__.'()'));
    }
    $rowc = array();

    $list = new PHUIObjectItemListView();
    foreach ($this->commits as $commit) {
      $commit_phid = $commit->getPHID();
      $commit_handle = $this->getHandle($commit_phid);
      $committed = null;

      $commit_name = $commit_handle->getName();
      $commit_link = $commit_handle->getURI();
      $commit_desc = $this->getCommitDescription($commit_phid);
      $committed = phabricator_datetime($commit->getEpoch(), $user);

      $audits = mpull($commit->getAudits(), null, 'getAuditorPHID');
      $auditors = array();
      $reasons = array();
      foreach ($audits as $audit) {
        $auditor_phid = $audit->getAuditorPHID();
        $auditors[$auditor_phid] =
          $this->getHandle($auditor_phid)->renderLink();
      }
      $auditors = phutil_implode_html(', ', $auditors);

      $authority_audits = array_select_keys($audits, $this->authorityPHIDs);
      if ($authority_audits) {
        $audit = reset($authority_audits);
      } else {
        $audit = reset($audits);
      }
      if ($audit) {
        $reasons = $audit->getAuditReasons();
        $reasons = phutil_implode_html(', ', $reasons);
        $status_code = $audit->getAuditStatus();
        $status_text =
          PhabricatorAuditStatusConstants::getStatusName($status_code);
        $status_color =
          PhabricatorAuditStatusConstants::getStatusColor($status_code);
      } else {
        $reasons = null;
        $status_text = null;
        $status_color = null;
      }
      $author_phid = $commit->getAuthorPHID();
      if ($author_phid) {
        $author_name = $this->getHandle($author_phid)->renderLink();
      } else {
        $author_name = $commit->getCommitData()->getAuthorName();
      }

      $item = id(new PHUIObjectItemView())
        ->setObjectName($commit_name)
        ->setHeader($commit_desc)
        ->setHref($commit_link)
        ->setBarColor($status_color)
        ->addAttribute($status_text)
        ->addAttribute($reasons)
        ->addIcon('none', $committed)
        ->setSubHead(pht('Author: %s', $author_name));

      if (!empty($auditors)) {
        $item->addByLine(pht('Auditors: %s', $auditors));
      }

      $list->addItem($item);
    }

    if ($this->noDataString) {
      $list->setNoDataString($this->noDataString);
    }

    return $list;
  }

}
