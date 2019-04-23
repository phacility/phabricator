<?php

abstract class DiffusionHistoryView extends DiffusionView {

  private $history;
  private $revisions = array();
  private $handles = array();
  private $isHead;
  private $isTail;
  private $parents;
  private $filterParents;
  private $revisionMap;

  public function setHistory(array $history) {
    assert_instances_of($history, 'DiffusionPathChange');
    $this->history = $history;
    return $this;
  }

  public function getHistory() {
    return $this->history;
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

  final protected function getRevisionsForCommit(
    PhabricatorRepositoryCommit $commit) {

    if ($this->revisionMap === null) {
      $this->revisionMap = $this->newRevisionMap();
    }

    return idx($this->revisionMap, $commit->getPHID(), array());
  }

  private function newRevisionMap() {
    $history = $this->history;

    $commits = array();
    foreach ($history as $item) {
      $commit = $item->getCommit();
      if ($commit) {

        // NOTE: The "commit" objects in the history list may be undiscovered,
        // and thus not yet have PHIDs. Only load data for commits with PHIDs.
        if (!$commit->getPHID()) {
          continue;
        }

        $commits[] = $commit;
      }
    }

    return DiffusionCommitRevisionQuery::loadRevisionMapForCommits(
      $this->getViewer(),
      $commits);
  }

}
