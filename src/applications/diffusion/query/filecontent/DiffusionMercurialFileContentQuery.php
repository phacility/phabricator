<?php

final class DiffusionMercurialFileContentQuery
  extends DiffusionFileContentQuery {

  public function getFileContentFuture() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    if ($this->getNeedsBlame()) {
      // NOTE: We're using "--number" instead of "--changeset" because there is
      // no way to get "--changeset" to show us the full commit hashes.
      return $repository->getLocalCommandFuture(
        'annotate --user --number --rev %s -- %s',
        $commit,
        $path);
    } else {
      return $repository->getLocalCommandFuture(
        'cat --rev %s -- %s',
        $commit,
        $path);
    }
  }

  protected function executeQueryFromFuture(Future $future) {
    list($corpus) = $future->resolvex();

    $file_content = new DiffusionFileContent();
    $file_content->setCorpus($corpus);

    return $file_content;
  }

  protected function tokenizeLine($line) {
    $matches = null;

    preg_match(
      '/^(.*?)\s+([0-9]+): (.*)$/',
      $line,
      $matches);

    return array($matches[2], $matches[1], $matches[3]);
  }

  /**
   * Convert local revision IDs into full commit identifier hashes.
   */
  protected function processRevList(array $rev_list) {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $revs = array_unique($rev_list);
    foreach ($revs as $key => $rev) {
      $revs[$key] = '--rev '.(int)$rev;
    }

    list($stdout) = $repository->execxLocalCommand(
      'log --template=%s %C',
      '{rev} {node}\\n',
      implode(' ', $revs));

    $map = array();
    foreach (explode("\n", trim($stdout)) as $line) {
      list($rev, $node) = explode(' ', $line);
      $map[$rev] = $node;
    }

    foreach ($rev_list as $k => $rev) {
      $rev_list[$k] = $map[$rev];
    }

    return $rev_list;
  }

}
