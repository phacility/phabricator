<?php

final class DiffusionMercurialStableCommitNameQuery
extends DiffusionStableCommitNameQuery {

  protected function executeQuery() {
    $repository = $this->getRepository();

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

    list($stable_commit_name) = $repository->execxLocalCommand(
      'log --template=%s -b %s --limit 1',
      '{node}',
      $this->getBranch());

    return $stable_commit_name;
  }

}
