<?php

abstract class DiffusionMergedCommitsQuery extends DiffusionQuery {

  private $limit = PHP_INT_MAX;

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {

    return self::newQueryObject(__CLASS__, $request);
  }

  final public function loadMergedCommits() {
    return $this->executeQuery();
  }

  final public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  final public function getLimit() {
    return $this->limit;
  }

}
