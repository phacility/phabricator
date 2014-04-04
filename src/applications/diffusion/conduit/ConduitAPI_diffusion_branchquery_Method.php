<?php

final class ConduitAPI_diffusion_branchquery_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function getMethodDescription() {
    return pht('Determine what branches exist for a repository.');
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  protected function defineCustomParamTypes() {
    return array(
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

    $refs = id(new DiffusionLowLevelMercurialBranchesQuery())
      ->setRepository($repository)
      ->execute();

    // If we have a 'contains' query, filter these branches down to just the
    // ones which contain the commit.
    $contains = $request->getValue('contains');
    if (strlen($contains)) {
      list($branches_raw) = $repository->execxLocalCommand(
        'log --template %s --limit 1 --rev %s --',
        '{branches}',
        hgsprintf('%s', $contains));

      $branches_raw = trim($branches_raw);
      if (!strlen($branches_raw)) {
        $containing_branches = array('default');
      } else {
        $containing_branches = explode(' ', $branches_raw);
      }

      $containing_branches = array_fuse($containing_branches);

      // NOTE: We get this very slightly wrong: a branch may have multiple
      // heads and we'll keep all of the heads of the branch, even if the
      // commit is only on some of the heads. This should be rare, is probably
      // more clear to users as is, and would potentially be expensive to get
      // right since we'd have to do additional checks.

      foreach ($refs as $key => $ref) {
        if (empty($containing_branches[$ref->getShortName()])) {
          unset($refs[$key]);
        }
      }
    }

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
