<?php

final class PhabricatorAuditCommitListView extends AphrontView {

  private $commits;
  private $handles;
  private $noDataString;

  public function setNoDataString($no_data_string) {
    $this->noDataString = $no_data_string;
    return $this;
  }

  public function setCommits(array $commits) {
    assert_instances_of($commits, 'PhabricatorRepositoryCommit');
    $this->commits = mpull($commits, null, 'getPHID');
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

  public function getRequiredHandlePHIDs() {
    $phids = array();
    foreach ($this->commits as $commit) {
      if ($commit->getAuthorPHID()) {
        $phids[$commit->getAuthorPHID()] = true;
      }
      $phids[$commit->getPHID()] = true;
      if ($commit->getAudits()) {
        foreach ($commit->getAudits() as $audit) {
          $phids[$audit->getActorPHID()] = true;
        }
      }
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

  public function render() {
    $list = new PHUIObjectItemListView();
    $list->setCards(true);
    $list->setFlush(true);
    foreach ($this->commits as $commit) {
      $commit_phid = $commit->getPHID();
      $commit_name = $this->getHandle($commit_phid)->getName();
      $commit_link = $this->getHandle($commit_phid)->getURI();
      $commit_desc = $this->getCommitDescription($commit_phid);

      $author_name = null;
      if ($commit->getAuthorPHID()) {
        $author_name = $this->getHandle($commit->getAuthorPHID())->renderLink();
      }
      $auditors = array();
      if ($commit->getAudits()) {
        foreach ($commit->getAudits() as $audit) {
          $actor_phid = $audit->getActorPHID();
          $auditors[$actor_phid] = $this->getHandle($actor_phid)->renderLink();
        }
        $auditors = phutil_implode_html(', ', $auditors);
      }
      $committed = phabricator_datetime($commit->getEpoch(), $this->user);
      $commit_status = PhabricatorAuditCommitStatusConstants::getStatusName(
          $commit->getAuditStatus());

      $item = id(new PHUIObjectItemView())
          ->setObjectName($commit_name)
          ->setHeader($commit_desc)
          ->setHref($commit_link)
          ->addAttribute($commit_status)
          ->addIcon('none', $committed);

      if (!empty($auditors)) {
        $item->addAttribute(pht('Auditors: %s', $auditors));
      }

      if ($author_name) {
        $item->addByline(pht('Author: %s', $author_name));
      }

      $list->addItem($item);
    }

    if ($this->noDataString) {
      $list->setNoDataString($this->noDataString);
    }

    return $list->render();
  }

}
