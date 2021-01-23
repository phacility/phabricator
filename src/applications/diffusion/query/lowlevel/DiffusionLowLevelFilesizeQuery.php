<?php

final class DiffusionLowLevelFilesizeQuery
  extends DiffusionLowLevelQuery {

  private $identifier;

  public function withIdentifier($identifier) {
    $this->identifier = $identifier;
    return $this;
  }

  protected function executeQuery() {
    if (!strlen($this->identifier)) {
      throw new PhutilInvalidStateException('withIdentifier');
    }

    $type = $this->getRepository()->getVersionControlSystem();
    switch ($type) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $result = $this->loadGitFilesizes();
        break;
      default:
        throw new Exception(pht('Unsupported repository type "%s"!', $type));
    }

    return $result;
  }

  private function loadGitFilesizes() {
    $repository = $this->getRepository();
    $identifier = $this->identifier;

    $paths_future = $repository->getLocalCommandFuture(
      'diff-tree -z -r --no-commit-id %s --',
      gitsprintf('%s', $identifier));

    // With "-z" we get "<fields>\0<filename>\0" for each line. Process the
    // delimited text as "<fields>, <filename>" pairs.

    $path_lines = id(new LinesOfALargeExecFuture($paths_future))
      ->setDelimiter("\0");

    $paths = array();

    $path_pairs = new PhutilChunkedIterator($path_lines, 2);
    foreach ($path_pairs as $path_pair) {
      if (count($path_pair) != 2) {
        throw new Exception(
          pht(
            'Unexpected number of output lines from "git diff-tree" when '.
            'processing commit ("%s"): expected an even number of lines.',
            $identifier));
      }

      list($fields, $pathname) = array_values($path_pair);
      $fields = explode(' ', $fields);

      // Fields are:
      //
      //    :100644 100644 aaaa bbbb M
      //
      // [0] Old file mode.
      // [1] New file mode.
      // [2] Old object hash.
      // [3] New object hash.
      // [4] Change mode.

      $paths[] = array(
        'path' => $pathname,
        'newHash' => $fields[3],
      );
    }

    $path_sizes = array();

    if (!$paths) {
      return $path_sizes;
    }

    $check_paths = array();
    foreach ($paths as $path) {
      if ($path['newHash'] === DiffusionCommitHookEngine::EMPTY_HASH) {
        $path_sizes[$path['path']] = 0;
        continue;
      }
      $check_paths[$path['newHash']][] = $path['path'];
    }

    if (!$check_paths) {
      return $path_sizes;
    }

    $future = $repository->getLocalCommandFuture(
      'cat-file --batch-check=%s',
      '%(objectsize)');

    $future->write(implode("\n", array_keys($check_paths)));

    $size_lines = id(new LinesOfALargeExecFuture($future))
      ->setDelimiter("\n");
    foreach ($size_lines as $line) {
      $object_size = (int)$line;

      $object_hash = head_key($check_paths);
      $path_names = $check_paths[$object_hash];
      unset($check_paths[$object_hash]);

      foreach ($path_names as $path_name) {
        $path_sizes[$path_name] = $object_size;
      }
    }

    if ($check_paths) {
      throw new Exception(
        pht(
          'Unexpected number of output lines from "git cat-file" when '.
          'processing commit ("%s").',
          $identifier));
    }

    return $path_sizes;
  }

}
