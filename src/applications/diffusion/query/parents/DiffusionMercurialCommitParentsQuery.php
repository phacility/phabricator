<?php

final class DiffusionMercurialCommitParentsQuery
  extends DiffusionCommitParentsQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    list($stdout) = $repository->execxLocalCommand(
      'log --debug --limit 1 --template={parents} --rev %s',
      $drequest->getStableCommitName());

    $hashes = preg_split('/\s+/', trim($stdout));
    foreach ($hashes as $key => $value) {
      // Mercurial parents look like "23:ad9f769d6f786fad9f76d9a" -- we want
      // to strip out the local rev part.
      list($local, $global) = explode(':', $value);
      $hashes[$key] = $global;

      // With --debug we get 40-character hashes but also get the "000000..."
      // hash for missing parents; ignore it.
      if (preg_match('/^0+$/', $global)) {
        unset($hashes[$key]);
      }
    }

    return self::loadCommitsByIdentifiers($hashes);
  }
}
