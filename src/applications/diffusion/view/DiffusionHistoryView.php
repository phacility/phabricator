<?php

abstract class DiffusionHistoryView extends DiffusionView {

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

  public function getHistory() {
    return $this->history;
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

  public function getRevisions() {
    return $this->revisions;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function getRequiredHandlePHIDs() {
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

  public function render() {}

}
