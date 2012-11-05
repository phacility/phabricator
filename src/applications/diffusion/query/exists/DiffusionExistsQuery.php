<?php

abstract class DiffusionExistsQuery extends DiffusionQuery {

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return self::newQueryObject(__CLASS__, $request);
  }

  final public function loadExistentialData() {
    return $this->executeQuery();
  }
}
