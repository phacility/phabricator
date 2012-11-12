<?php

abstract class DiffusionTagListQuery extends DiffusionQuery {

  private $limit;
  private $offset;

  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  public function getOffset() {
    return $this->offset;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  protected function getLimit() {
    return $this->limit;
  }

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return self::newQueryObject(__CLASS__, $request);
  }

  final public function loadTags() {
    return $this->executeQuery();
  }

}
