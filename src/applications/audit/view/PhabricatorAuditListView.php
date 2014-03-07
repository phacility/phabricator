<?php

final class PhabricatorAuditListView extends AphrontView {

  private $audits;
  private $handles;
  private $authorityPHIDs = array();
  private $noDataString;
  private $commits;
  private $showCommits = true;

  private $highlightedAudits;

  public function setAudits(array $audits) {
    assert_instances_of($audits, 'PhabricatorRepositoryAuditRequest');
    $this->audits = $audits;
    return $this;
  }

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

  public function setCommits(array $commits) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');
    $this->commits = mpull($commits, null, 'getPHID');
    return $this;
  }

  public function setShowCommits($show_commits) {
    $this->showCommits = $show_commits;
    return $this;
  }

  public function getRequiredHandlePHIDs() {
    $phids = array();
    foreach ($this->audits as $audit) {
      $phids[$audit->getCommitPHID()] = true;
      $phids[$audit->getAuditorPHID()] = true;
    }
    return array_keys($phids);
  }

  private function getHandle($phid) {
    $handle = idx($this->handles, $phid);
    if (!$handle) {
      throw new Exception("No handle for '{$phid}'!");
    }
    return $handle;
  }

  private function getCommitDescription($phid) {
    if ($this->commits === null) {
      return null;
    }

    $commit = idx($this->commits, $phid);
    if (!$commit) {
      return null;
    }

    return $commit->getCommitData()->getSummary();
  }

  public function getHighlightedAudits() {
    if ($this->highlightedAudits === null) {
      $this->highlightedAudits = array();

      $user = $this->user;
      $authority = array_fill_keys($this->authorityPHIDs, true);

      foreach ($this->audits as $audit) {
        $has_authority = !empty($authority[$audit->getAuditorPHID()]);
        if ($has_authority) {
          $commit_phid = $audit->getCommitPHID();
          $commit_author = $this->commits[$commit_phid]->getAuthorPHID();

          // You don't have authority over package and project audits on your
          // own commits.

          $auditor_is_user = ($audit->getAuditorPHID() == $user->getPHID());
          $user_is_author = ($commit_author == $user->getPHID());

          if ($auditor_is_user || !$user_is_author) {
            $this->highlightedAudits[$audit->getID()] = $audit;
          }
        }
      }
    }

    return $this->highlightedAudits;
  }

  public function render() {
    $rowc = array();

    $list = new PHUIObjectItemListView();
    $list->setCards(true);
    $list->setFlush(true);
    foreach ($this->audits as $audit) {
      $commit_phid = $audit->getCommitPHID();
      $committed = null;

      $commit_name = $this->getHandle($commit_phid)->getName();
      $commit_link = $this->getHandle($commit_phid)->getURI();
      $commit_desc = $this->getCommitDescription($commit_phid);
      $commit = idx($this->commits, $commit_phid);
      if ($commit && $this->user) {
        $committed = phabricator_datetime($commit->getEpoch(), $this->user);
      }

      $reasons = $audit->getAuditReasons();
      $reasons = phutil_implode_html(', ', $reasons);

      $status_code = $audit->getAuditStatus();
      $status_text =
        PhabricatorAuditStatusConstants::getStatusName($status_code);
      $status_color =
        PhabricatorAuditStatusConstants::getStatusColor($status_code);

      $author_name = $commit->getCommitData()->getAuthorName();

      $auditor_handle = $this->getHandle($audit->getAuditorPHID());
      $item = id(new PHUIObjectItemView())
          ->setObjectName($commit_name)
          ->setHeader($commit_desc)
          ->setHref($commit_link)
          ->setBarColor($status_color)
          ->addAttribute($status_text)
          ->addAttribute($reasons)
          ->addAttribute(pht('Author: %s', $author_name))
          ->addIcon('none', $committed)
          ->addByline(pht('Auditor: %s', $auditor_handle->renderLink()));

      if (array_key_exists($audit->getID(), $this->getHighlightedAudits())) {
        $item->setEffect('highlighted');
      }

      $list->addItem($item);
    }

    if ($this->noDataString) {
      $list->setNoDataString($this->noDataString);
    }

    return $list->render();
  }

}
