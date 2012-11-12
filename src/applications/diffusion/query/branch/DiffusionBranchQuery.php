<?php

abstract class DiffusionBranchQuery {

  private $request;
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

  final private function __construct() {
    // <private>
  }

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {

    $repository = $request->getRepository();

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $query = new DiffusionGitBranchQuery();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $query = new DiffusionMercurialBranchQuery();
        break;
      default:
        throw new Exception("Unsupported VCS!");
    }

    $query->request = $request;

    return $query;
  }

  final protected function getRequest() {
    return $this->request;
  }

  final public function loadBranches() {
    return $this->executeQuery();
  }

  abstract protected function executeQuery();
}
