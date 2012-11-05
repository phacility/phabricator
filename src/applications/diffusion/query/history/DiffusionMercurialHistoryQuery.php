<?php

final class DiffusionMercurialHistoryQuery extends DiffusionHistoryQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit_hash = $drequest->getStableCommitName();

    $path = DiffusionPathIDQuery::normalizePath($path);
    $path = ltrim($path, '/');

    // NOTE: Older versions of Mercurial give different results for these
    // commands (see T1268):
    //
    //   $ hg log -- ''
    //   $ hg log
    //
    // All versions of Mercurial give different results for these commands
    // (merge commits are excluded with the "." version):
    //
    //   $ hg log -- .
    //   $ hg log
    //
    // If we don't have a path component in the query, omit it from the command
    // entirely to avoid these inconsistencies.

    $path_arg = '';
    if (strlen($path)) {
      $path_arg = csprintf('-- %s', $path);
    }

    // NOTE: --branch used to be called --only-branch; use -b for compatibility.
    list($stdout) = $repository->execxLocalCommand(
      'log --debug --template %s --limit %d -b %s --rev %s:0 %C',
      '{node};{parents}\\n',
      ($this->getOffset() + $this->getLimit()), // No '--skip' in Mercurial.
      $drequest->getBranch(),
      $commit_hash,
      $path_arg);

    $lines = explode("\n", trim($stdout));
    $lines = array_slice($lines, $this->getOffset());

    $hash_list = array();
    $parent_map = array();

    $last = null;
    foreach (array_reverse($lines) as $line) {
      list($hash, $parents) = explode(';', $line);
      $parents = trim($parents);
      if (!$parents) {
        if ($last === null) {
          $parent_map[$hash] = array('...');
        } else {
          $parent_map[$hash] = array($last);
        }
      } else {
        $parents = preg_split('/\s+/', $parents);
        foreach ($parents as $parent) {
          list($plocal, $phash) = explode(':', $parent);
          if (!preg_match('/^0+$/', $phash)) {
            $parent_map[$hash][] = $phash;
          }
        }
        // This may happen for the zeroth commit in repository, both hashes
        // are "000000000...".
        if (empty($parent_map[$hash])) {
          $parent_map[$hash] = array('...');
        }
      }

      // The rendering code expects the first commit to be "mainline", like
      // Git. Flip the order so it does the right thing.
      $parent_map[$hash] = array_reverse($parent_map[$hash]);

      $hash_list[] = $hash;
      $last = $hash;
    }

    $hash_list = array_reverse($hash_list);
    $this->parents = $parent_map;

    return $this->loadHistoryForCommitIdentifiers($hash_list);
  }

}
