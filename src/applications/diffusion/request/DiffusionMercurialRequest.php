<?php

/**
 * @group diffusion
 */
final class DiffusionMercurialRequest extends DiffusionRequest {

  protected function getSupportsBranches() {
    return true;
  }

  protected function didInitialize() {
    $repository = $this->getRepository();

    $this->validateWorkingCopy($repository->getLocalPath());

    // Expand abbreviated hashes to full hashes so "/rXnnnn" (i.e., fewer than
    // 40 characters) works correctly.
    if (!$this->commit) {
      return;
    }

    if (strlen($this->commit) == 40) {
      return;
    }

    list($full_hash) = $this->repository->execxLocalCommand(
      'log --template=%s --rev %s',
      '{node}',
      $this->commit);

    $full_hash = explode("\n", trim($full_hash));

    // TODO: Show "multiple matching commits" if count is larger than 1. For
    // now, pick the first one.

    $this->commit = head($full_hash);


    return;
  }

  public function getBranch() {
    if ($this->branch) {
      return $this->branch;
    }

    if ($this->repository) {
      return $this->repository->getDefaultBranch();
    }

    throw new Exception("Unable to determine branch!");
  }

  public function getCommit() {
    if ($this->commit) {
      return $this->commit;
    }
    return $this->getBranch();
  }

  public function getStableCommitName() {
    if (!$this->stableCommitName) {
      if ($this->commit) {
        $this->stableCommitName = $this->commit;
      } else {

        // NOTE: For branches with spaces in their name like "a b", this
        // does not work properly:
        //
        //   $ hg log --rev 'a b'
        //
        // We can use revsets instead:
        //
        //   $ hg log --rev branch('a b')
        //
        // ...but they require a somewhat newer version of Mercurial. Instead,
        // use "-b" flag with limit 1 for greatest compatibility across
        // versions.

        list($this->stableCommitName) = $this->repository->execxLocalCommand(
          'log --template=%s -b %s --limit 1',
          '{node}',
          $this->getBranch());
      }
    }
    return $this->stableCommitName;
  }

}
