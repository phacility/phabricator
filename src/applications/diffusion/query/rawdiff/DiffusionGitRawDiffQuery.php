<?php

final class DiffusionGitRawDiffQuery extends DiffusionRawDiffQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $commit = $this->getAnchorCommit();

    $options = array(
      '-M',
      '-C',
      '--no-ext-diff',
      '--no-color',
      '--src-prefix=a/',
      '--dst-prefix=b/',
      '-U'.(int)$this->getLinesOfContext(),
    );
    $options = implode(' ', $options);

    $against = $this->getAgainstCommit();
    if ($against === null) {
      $against = $commit.'^';
    }

    // If there's no path, get the entire raw diff.
    $path = nonempty($drequest->getPath(), '.');

    $future = $repository->getLocalCommandFuture(
      'diff %C %s %s -- %s',
      $options,
      $against,
      $commit,
      $path);

    $this->configureFuture($future);

    try {
      list($raw_diff) = $future->resolvex();
    } catch (CommandException $ex) {
      // Check if this is the root commit by seeing if it has parents.
      list($parents) = $repository->execxLocalCommand(
        'log --format=%s %s --',
        '%P', // "parents"
        $commit);

      if (strlen(trim($parents))) {
        throw $ex;
      }

      // No parents means we're looking at the root revision. Diff against
      // the empty tree hash instead, since there is no parent so "^" does
      // not work. See ArcanistGitAPI for more discussion.
      $future = $repository->getLocalCommandFuture(
        'diff %C %s %s -- %s',
        $options,
        ArcanistGitAPI::GIT_MAGIC_ROOT_COMMIT,
        $commit,
        $drequest->getPath());

      $this->configureFuture($future);

      list($raw_diff) = $future->resolvex();
    }

    return $raw_diff;
  }

}
