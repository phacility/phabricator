<?php

final class DiffusionInternalGitRawDiffQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function isInternalAPI() {
    return true;
  }

  public function getAPIMethodName() {
    return 'diffusion.internal.gitrawdiffquery';
  }

  public function getMethodDescription() {
    return pht('Internal method for getting raw diff information.');
  }

  protected function defineReturnType() {
    return 'string';
  }

  protected function defineCustomParamTypes() {
    return array(
      'commit' => 'required string',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    if (!$repository->isGit()) {
      throw new Exception(
        pht(
          'This API method can only be called on Git repositories.'));
    }

    // Check if the commit has parents. We're testing to see whether it is the
    // first commit in history (in which case we must use "git log") or some
    // other commit (in which case we can use "git diff"). We'd rather use
    // "git diff" because it has the right behavior for merge commits, but
    // it requires the commit to have a parent that we can diff against. The
    // first commit doesn't, so "commit^" is not a valid ref.
    list($parents) = $repository->execxLocalCommand(
      'log -n1 --format=%s %s',
      '%P',
      $request->getValue('commit'));

    $use_log = !strlen(trim($parents));
    if ($use_log) {
      // This is the first commit so we need to use "log". We know it's not a
      // merge commit because it couldn't be merging anything, so this is safe.

      // NOTE: "--pretty=format: " is to disable diff output, we only want the
      // part we get from "--raw".
      list($raw) = $repository->execxLocalCommand(
        'log -n1 -M -C -B --find-copies-harder --raw -t '.
          '--pretty=format: --abbrev=40 %s',
        $request->getValue('commit'));
    } else {
      // Otherwise, we can use "diff", which will give us output for merges.
      // We diff against the first parent, as this is generally the expectation
      // and results in sensible behavior.
      list($raw) = $repository->execxLocalCommand(
        'diff -n1 -M -C -B --find-copies-harder --raw -t '.
          '--abbrev=40 %s^1 %s',
        $request->getValue('commit'),
        $request->getValue('commit'));
    }

    return $raw;
  }

}
