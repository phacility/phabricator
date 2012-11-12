<?php

abstract class DiffusionCommitParentsQuery extends DiffusionQuery {

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return self::newQueryObject(__CLASS__, $request);
  }

  final public function loadParents() {
    return $this->executeQuery();
  }

}
