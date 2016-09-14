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
    $commit = $request->getValue('commit');

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
      $commit);
    $use_log = !strlen(trim($parents));

    // First, get a fast raw diff without "--find-copies-harder". This flag
    // produces better results for moves and copies, but is explosively slow
    // for large changes to large repositories. See T10423.
    $raw = $this->getRawDiff($repository, $commit, $use_log, false);

    // If we got a normal-sized diff (no more than 100 modified files), we'll
    // try using "--find-copies-harder" to improve the output. This improved
    // output is mostly useful for small changes anyway.
    $try_harder = (substr_count($raw, "\n") <= 100);
    if ($try_harder) {
      try {
        $raw = $this->getRawDiff($repository, $commit, $use_log, true);
      } catch (Exception $ex) {
        // Just ignore any exception we hit, we'll use the fast output
        // instead.
      }
    }

    return $raw;
  }

  private function getRawDiff(
    PhabricatorRepository $repository,
    $commit,
    $use_log,
    $try_harder) {

    $flags = array(
      '-n1',
      '-M',
      '-C',
      '-B',
      '--raw',
      '-t',
      '--abbrev=40',
    );

    if ($try_harder) {
      $flags[] = '--find-copies-harder';
    }

    if ($use_log) {
      // This is the first commit so we need to use "log". We know it's not a
      // merge commit because it couldn't be merging anything, so this is safe.

      // NOTE: "--pretty=format: " is to disable diff output, we only want the
      // part we get from "--raw".
      $future = $repository->getLocalCommandFuture(
        'log %Ls --pretty=format: %s',
        $flags,
        $commit);
    } else {
      // Otherwise, we can use "diff", which will give us output for merges.
      // We diff against the first parent, as this is generally the expectation
      // and results in sensible behavior.
      $future = $repository->getLocalCommandFuture(
        'diff %Ls %s^1 %s',
        $flags,
        $commit,
        $commit);
    }

    // Don't spend more than 30 seconds generating the slower output.
    if ($try_harder) {
      $future->setTimeout(30);
    }

    list($raw) = $future->resolvex();

    return $raw;
  }

}
