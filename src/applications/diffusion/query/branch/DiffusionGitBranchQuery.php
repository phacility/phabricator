<?php

final class DiffusionGitBranchQuery extends DiffusionBranchQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    // We need to add 1 in case we pick up HEAD.

    $count = $this->getOffset() + $this->getLimit() + 1;

    list($stdout) = $repository->execxLocalCommand(
      'for-each-ref %C --sort=-creatordate --format=%s refs/remotes',
      $count ? '--count='.(int)$count : null,
      '%(refname:short) %(objectname)'
    );

    $branch_list = self::parseGitRemoteBranchOutput(
      $stdout,
      $only_this_remote = DiffusionBranchInformation::DEFAULT_GIT_REMOTE);

    $branches = array();
    foreach ($branch_list as $name => $head) {
      if (!$repository->shouldTrackBranch($name)) {
        continue;
      }

      $branch = new DiffusionBranchInformation();
      $branch->setName($name);
      $branch->setHeadCommitIdentifier($head);
      $branches[] = $branch;
    }

    $offset = $this->getOffset();
    if ($offset) {
      $branches = array_slice($branches, $offset);
    }

    // We might have too many even after offset slicing, if there was no HEAD
    // for some reason.
    $limit = $this->getLimit();
    if ($limit) {
      $branches = array_slice($branches, 0, $limit);
    }

    return $branches;
  }


  /**
   * Parse the output of 'git branch -r --verbose --no-abbrev' or similar into
   * a map. For instance:
   *
   *   array(
   *     'origin/master' => '99a9c082f9a1b68c7264e26b9e552484a5ae5f25',
   *   );
   *
   * If you specify $only_this_remote, branches will be filtered to only those
   * on the given remote, **and the remote name will be stripped**. For example:
   *
   *   array(
   *     'master' => '99a9c082f9a1b68c7264e26b9e552484a5ae5f25',
   *   );
   *
   * @param string stdout of git branch command.
   * @param string Filter branches to those on a specific remote.
   * @return map Map of 'branch' or 'remote/branch' to hash at HEAD.
   */
  public static function parseGitRemoteBranchOutput(
    $stdout,
    $only_this_remote = null) {
    $map = array();

    $lines = array_filter(explode("\n", $stdout));
    foreach ($lines as $line) {
      $matches = null;
      if (preg_match('/^  (\S+)\s+-> (\S+)$/', $line, $matches)) {
          // This is a line like:
          //
          //   origin/HEAD          -> origin/master
          //
          // ...which we don't currently do anything interesting with, although
          // in theory we could use it to automatically choose the default
          // branch.
          continue;
      }
      if (!preg_match('/^ *(\S+)\s+([a-z0-9]{40})/', $line, $matches)) {
        throw new Exception("Failed to parse {$line}!");
      }

      $remote_branch = $matches[1];
      $branch_head = $matches[2];

      if (strpos($remote_branch, 'HEAD') !== false) {
        // let's assume that no one will call their remote or branch HEAD
        continue;
      }

      if ($only_this_remote) {
        $matches = null;
        if (!preg_match('#^([^/]+)/(.*)$#', $remote_branch, $matches)) {
          throw new Exception(
            "Failed to parse remote branch '{$remote_branch}'!");
        }
        $remote_name = $matches[1];
        $branch_name = $matches[2];
        if ($remote_name != $only_this_remote) {
          continue;
        }
        $map[$branch_name] = $branch_head;
      } else {
        $map[$remote_branch] = $branch_head;
      }
    }

    return $map;
  }

}
