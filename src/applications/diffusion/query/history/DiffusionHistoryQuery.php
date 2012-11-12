<?php

abstract class DiffusionHistoryQuery extends DiffusionQuery {

  private $limit = 100;
  private $offset = 0;

  protected $needDirectChanges;
  protected $needChildChanges;
  protected $needParents;

  protected $parents = array();

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {

    return parent::newQueryObject(__CLASS__, $request);
  }

  final public function needDirectChanges($direct) {
    $this->needDirectChanges = $direct;
    return $this;
  }

  final public function needChildChanges($child) {
    $this->needChildChanges = $child;
    return $this;
  }

  final public function needParents($parents) {
    $this->needParents = $parents;
    return $this;
  }

  final public function getParents() {
    if (!$this->needParents) {
      throw new Exception('Specify needParents() before calling getParents()!');
    }
    return $this->parents;
  }

  final public function loadHistory() {
    return $this->executeQuery();
  }

  final public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  final public function getLimit() {
    return $this->limit;
  }

  final public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  final public function getOffset() {
    return $this->offset;
  }

}
