<?php

abstract class DiffusionLastModifiedQuery extends DiffusionQuery {

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return parent::newQueryObject(__CLASS__, $request);
  }

  final public function loadLastModification() {
    return $this->executeQuery();
  }

}
