<?php

final class DiffusionGitBranch extends Phobject {

  const DEFAULT_GIT_REMOTE = 'origin';

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
  public static function parseRemoteBranchOutput(
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
        throw new Exception(
          pht(
            'Failed to parse %s!',
            $line));
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
            pht(
              "Failed to parse remote branch '%s'!",
              $remote_branch));
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

  /**
   * As above, but with no `-r`. Used for bare repositories.
   */
  public static function parseLocalBranchOutput($stdout) {
    $map = array();

    $lines = array_filter(explode("\n", $stdout));
    $regex = '/^[* ]*(\(no branch\)|\S+)\s+([a-z0-9]{40})/';
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match($regex, $line, $matches)) {
        throw new Exception(
          pht(
            'Failed to parse %s!',
            $line));
      }

      $branch = $matches[1];
      $branch_head = $matches[2];
      if ($branch == '(no branch)') {
        continue;
      }

      $map[$branch] = $branch_head;
    }

    return $map;
  }

}
