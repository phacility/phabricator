<?php

abstract class DiffusionDiffQuery extends DiffusionQuery {

  protected $renderingReference;

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return parent::newQueryObject(__CLASS__, $request);
  }

  final public function getRenderingReference() {
    return $this->renderingReference;
  }

  final public function loadChangeset() {
    return $this->executeQuery();
  }

  protected function getEffectiveCommit() {
    $drequest = $this->getRequest();

    $modified_query = DiffusionLastModifiedQuery::newFromDiffusionRequest(
      $drequest);
    list($commit) = $modified_query->loadLastModification();
    if (!$commit) {
      // TODO: Improve error messages here.
      return null;
    }
    return $commit->getCommitIdentifier();
  }

}
