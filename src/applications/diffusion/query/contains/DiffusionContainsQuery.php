<?php

abstract class DiffusionContainsQuery extends DiffusionQuery {

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return self::newQueryObject(__CLASS__, $request);
  }

  final public function loadContainingBranches() {
    return $this->executeQuery();
  }

}
