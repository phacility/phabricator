<?php

final class DiffusionMergedCommitsQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.mergedcommitsquery';
  }

  public function getMethodDescription() {
    return pht(
      'Merged commit information for a specific commit in a repository.');
  }

  protected function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'commit' => 'required string',
      'limit' => 'optional int',
    );
  }

  private function getLimit(ConduitAPIRequest $request) {
    // TODO: Paginate this sensibly at some point.
    return $request->getValue('limit', 4096);
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $commit = $request->getValue('commit');
    $limit = $this->getLimit($request);

    list($parents) = $repository->execxLocalCommand(
      'log -n 1 %s %s --',
      '--format=%P',
      gitsprintf('%s', $commit));

    $parents = preg_split('/\s+/', trim($parents));
    if (count($parents) < 2) {
      // This is not a merge commit, so it doesn't merge anything.
      return array();
    }

    // Get all of the commits which are not reachable from the first parent.
    // These are the commits this change merges.

    $first_parent = head($parents);
    list($logs) = $repository->execxLocalCommand(
      'log -n %d %s %s %s --',
      // NOTE: "+ 1" accounts for the merge commit itself.
      $limit + 1,
      '--format=%H',
      gitsprintf('%s', $commit),
      gitsprintf('%s', '^'.$first_parent));

    $hashes = explode("\n", trim($logs));

    // Remove the merge commit.
    $hashes = array_diff($hashes, array($commit));

    $history = DiffusionQuery::loadHistoryForCommitIdentifiers(
      $hashes,
      $drequest);
    return mpull($history, 'toDictionary');
   }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $commit = $request->getValue('commit');
    $limit = $this->getLimit($request);

    list($parents) = $repository->execxLocalCommand(
      'parents --template=%s --rev %s',
      '{node}\\n',
      hgsprintf('%s', $commit));
    $parents = explode("\n", trim($parents));

    if (count($parents) < 2) {
      // Not a merge commit.
      return array();
    }

    // NOTE: In Git, the first parent is the "mainline". In Mercurial, the
    // second parent is the "mainline" (the way 'git merge' and 'hg merge'
    // work is also reversed).

    $last_parent = last($parents);
    list($logs) = $repository->execxLocalCommand(
      'log --template=%s --follow --limit %d --rev %s:0 --prune %s --',
      '{node}\\n',
      $limit + 1,
      $commit,
      $last_parent);

    $hashes = explode("\n", trim($logs));

    // Remove the merge commit.
    $hashes = array_diff($hashes, array($commit));

    $history = DiffusionQuery::loadHistoryForCommitIdentifiers(
      $hashes,
      $drequest);
    return mpull($history, 'toDictionary');
  }

}
