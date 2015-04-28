<?php

final class DiffusionBranchQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.branchquery';
  }

  public function getMethodDescription() {
    return pht('Determine what branches exist for a repository.');
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function defineCustomParamTypes() {
    return array(
      'closed' => 'optional bool',
      'limit' => 'optional int',
      'offset' => 'optional int',
      'contains' => 'optional string',
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $contains = $request->getValue('contains');
    if (strlen($contains)) {
      // NOTE: We can't use DiffusionLowLevelGitRefQuery here because
      // `git for-each-ref` does not support `--contains`.
      if ($repository->isWorkingCopyBare()) {
        list($stdout) = $repository->execxLocalCommand(
          'branch --verbose --no-abbrev --contains %s --',
          $contains);
        $ref_map = DiffusionGitBranch::parseLocalBranchOutput(
          $stdout);
      } else {
        list($stdout) = $repository->execxLocalCommand(
          'branch -r --verbose --no-abbrev --contains %s --',
          $contains);
        $ref_map = DiffusionGitBranch::parseRemoteBranchOutput(
          $stdout,
          DiffusionGitBranch::DEFAULT_GIT_REMOTE);
      }

      $refs = array();
      foreach ($ref_map as $ref => $commit) {
        $refs[] = id(new DiffusionRepositoryRef())
          ->setShortName($ref)
          ->setCommitIdentifier($commit);
      }
    } else {
      $refs = id(new DiffusionLowLevelGitRefQuery())
        ->setRepository($repository)
        ->withIsOriginBranch(true)
        ->execute();
    }

    return $this->processBranchRefs($request, $refs);
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $query = id(new DiffusionLowLevelMercurialBranchesQuery())
      ->setRepository($repository);

    $contains = $request->getValue('contains');
    if (strlen($contains)) {
      $query->withContainsCommit($contains);
    }

    $refs = $query->execute();

    return $this->processBranchRefs($request, $refs);
  }

  protected function getSVNResult(ConduitAPIRequest $request) {
    // Since SVN doesn't have meaningful branches, just return nothing for all
    // queries.
    return array();
  }

  private function processBranchRefs(ConduitAPIRequest $request, array $refs) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $offset = $request->getValue('offset');
    $limit = $request->getValue('limit');

    foreach ($refs as $key => $ref) {
      if (!$repository->shouldTrackBranch($ref->getShortName())) {
        unset($refs[$key]);
      }
    }

    $with_closed = $request->getValue('closed');
    if ($with_closed !== null) {
      foreach ($refs as $key => $ref) {
        $fields = $ref->getRawFields();
        if (idx($fields, 'closed') != $with_closed) {
          unset($refs[$key]);
        }
      }
    }

    // NOTE: We can't apply the offset or limit until here, because we may have
    // filtered untrackable branches out of the result set.

    if ($offset) {
      $refs = array_slice($refs, $offset);
    }

    if ($limit) {
      $refs = array_slice($refs, 0, $limit);
    }

    return mpull($refs, 'toDictionary');
  }

}
