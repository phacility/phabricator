<?php

final class DiffusionGitRawDiffQuery extends DiffusionRawDiffQuery {

  protected function newQueryFuture() {
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

    $against = $this->getAgainstCommit();
    if ($against === null) {
      // Check if this is the root commit by seeing if it has parents, since
      // `git diff X^ X` does not work if "X" is the initial commit.
      list($parents) = $repository->execxLocalCommand(
        'log -n 1 %s %s --',
        '--format=%P',
        gitsprintf('%s', $commit));

      if (strlen(trim($parents))) {
        $against = $commit.'^';
      } else {
        $against = ArcanistGitAPI::GIT_MAGIC_ROOT_COMMIT;
      }
    }

    $path = $drequest->getPath();
    if ($path === null || !strlen($path)) {
      $path = '.';
    }

    return $repository->getLocalCommandFuture(
      'diff %Ls %s %s -- %s',
      $options,
      gitsprintf('%s', $against),
      gitsprintf('%s', $commit),
      $path);
  }

}
