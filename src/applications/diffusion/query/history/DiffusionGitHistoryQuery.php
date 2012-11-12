<?php

final class DiffusionGitHistoryQuery extends DiffusionHistoryQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit_hash = $drequest->getCommit();

    list($stdout) = $repository->execxLocalCommand(
      'log '.
        '--skip=%d '.
        '-n %d '.
        '--pretty=format:%s '.
        '%s -- %C',
      $this->getOffset(),
      $this->getLimit(),
      '%H:%P',
      $commit_hash,
      // Git omits merge commits if the path is provided, even if it is empty.
      (strlen($path) ? csprintf('%s', $path) : ''));

    $lines = explode("\n", trim($stdout));
    $lines = array_filter($lines);
    if (!$lines) {
      return array();
    }

    $hash_list = array();
    $parent_map = array();
    foreach ($lines as $line) {
      list($hash, $parents) = explode(":", $line);
      $hash_list[] = $hash;
      $parent_map[$hash] = preg_split('/\s+/', $parents);
    }

    $this->parents = $parent_map;

    return $this->loadHistoryForCommitIdentifiers($hash_list);
  }

}
