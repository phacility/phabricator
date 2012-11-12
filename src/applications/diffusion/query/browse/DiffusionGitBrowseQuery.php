<?php

final class DiffusionGitBrowseQuery extends DiffusionBrowseQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    if ($path == '') {
      // Fast path to improve the performance of the repository view; we know
      // the root is always a tree at any commit and always exists.
      $stdout = 'tree';
    } else {
      try {
        list($stdout) = $repository->execxLocalCommand(
          'cat-file -t %s:%s',
          $commit,
          $path);
      } catch (CommandException $e) {
        $stderr = $e->getStdErr();
        if (preg_match('/^fatal: Not a valid object name/', $stderr)) {
          // Grab two logs, since the first one is when the object was deleted.
          list($stdout) = $repository->execxLocalCommand(
            'log -n2 --format="%%H" %s -- %s',
            $commit,
            $path);
          $stdout = trim($stdout);
          if ($stdout) {
            $commits = explode("\n", $stdout);
            $this->reason = self::REASON_IS_DELETED;
            $this->deletedAtCommit = idx($commits, 0);
            $this->existedAtCommit = idx($commits, 1);
            return array();
          }

          $this->reason = self::REASON_IS_NONEXISTENT;
          return array();
        } else {
          throw $e;
        }
      }
    }

    if (trim($stdout) == 'blob') {
      $this->reason = self::REASON_IS_FILE;
      return array();
    }

    if ($this->shouldOnlyTestValidity()) {
      return true;
    }

    list($stdout) = $repository->execxLocalCommand(
      'ls-tree -z -l %s:%s',
      $commit,
      $path);

    $submodules = array();

    $results = array();
    foreach (explode("\0", rtrim($stdout)) as $line) {

      // NOTE: Limit to 5 components so we parse filenames with spaces in them
      // correctly.
      list($mode, $type, $hash, $size, $name) = preg_split('/\s+/', $line, 5);

      $result = new DiffusionRepositoryPath();

      if ($type == 'tree') {
        $file_type = DifferentialChangeType::FILE_DIRECTORY;
      } else if ($type == 'commit') {
        $file_type = DifferentialChangeType::FILE_SUBMODULE;
        $submodules[] = $result;
      } else {
        $mode = intval($mode, 8);
        if (($mode & 0120000) == 0120000) {
          $file_type = DifferentialChangeType::FILE_SYMLINK;
        } else {
          $file_type = DifferentialChangeType::FILE_NORMAL;
        }
      }

      $result->setFullPath($path.$name);
      $result->setPath($name);
      $result->setHash($hash);
      $result->setFileType($file_type);
      $result->setFileSize($size);

      $results[] = $result;
    }

    // If we identified submodules, lookup the module info at this commit to
    // find their source URIs.

    if ($submodules) {

      // NOTE: We need to read the file out of git and write it to a temporary
      // location because "git config -f" doesn't accept a "commit:path"-style
      // argument.

      // NOTE: This file may not exist, e.g. because the commit author removed
      // it when they added the submodule. See T1448. If it's not present, just
      // show the submodule without enriching it. If ".gitmodules" was removed
      // it seems to partially break submodules, but the repository as a whole
      // continues to work fine and we've seen at least two cases of this in
      // the wild.

      list($err, $contents) = $repository->execLocalCommand(
        'cat-file blob %s:.gitmodules',
        $commit);

      if (!$err) {
        $tmp = new TempFile();
        Filesystem::writeFile($tmp, $contents);
        list($module_info) = $repository->execxLocalCommand(
          'config -l -f %s',
          $tmp);

        $dict = array();
        $lines = explode("\n", trim($module_info));
        foreach ($lines as $line) {
          list($key, $value) = explode('=', $line, 2);
          $parts = explode('.', $key);
          $dict[$key] = $value;
        }

        foreach ($submodules as $path) {
          $full_path = $path->getFullPath();
          $key = 'submodule.'.$full_path.'.url';
          if (isset($dict[$key])) {
            $path->setExternalURI($dict[$key]);
          }
        }
      }
    }

    return $results;
  }

}
