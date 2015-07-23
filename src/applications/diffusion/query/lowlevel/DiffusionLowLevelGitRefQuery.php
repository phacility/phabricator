<?php

/**
 * Execute and parse a low-level Git ref query using `git for-each-ref`. This
 * is useful for returning a list of tags or branches.
 */
final class DiffusionLowLevelGitRefQuery extends DiffusionLowLevelQuery {

  private $isTag;
  private $isOriginBranch;

  public function withIsTag($is_tag) {
    $this->isTag = $is_tag;
    return $this;
  }

  public function withIsOriginBranch($is_origin_branch) {
    $this->isOriginBranch = $is_origin_branch;
    return $this;
  }

  protected function executeQuery() {
    $repository = $this->getRepository();

    $prefixes = array();

    $any = ($this->isTag || $this->isOriginBranch);
    if (!$any) {
      throw new Exception(pht('Specify types of refs to query.'));
    }

    if ($this->isOriginBranch) {
      if ($repository->isWorkingCopyBare()) {
        $prefix = 'refs/heads/';
      } else {
        $remote = DiffusionGitBranch::DEFAULT_GIT_REMOTE;
        $prefix = 'refs/remotes/'.$remote.'/';
      }
      $prefixes[] = $prefix;
    }

    if ($this->isTag) {
      $prefixes[] = 'refs/tags/';
    }

    $order = '-creatordate';

    $futures = array();
    foreach ($prefixes as $prefix) {
      $futures[$prefix] = $repository->getLocalCommandFuture(
        'for-each-ref --sort=%s --format=%s %s',
        $order,
        $this->getFormatString(),
        $prefix);
    }

    // Resolve all the futures first. We want to iterate over them in prefix
    // order, not resolution order.
    foreach (new FutureIterator($futures) as $prefix => $future) {
      $future->resolvex();
    }

    $results = array();
    foreach ($futures as $prefix => $future) {
      list($stdout) = $future->resolvex();

      $stdout = rtrim($stdout);
      if (!strlen($stdout)) {
        continue;
      }

      // NOTE: Although git supports --count, we can't apply any offset or
      // limit logic until the very end because we may encounter a HEAD which
      // we want to discard.

      $lines = explode("\n", $stdout);
      foreach ($lines as $line) {
        $fields = $this->extractFields($line);

        $creator = $fields['creator'];
        $matches = null;
        if (preg_match('/^(.*) ([0-9]+) ([0-9+-]+)$/', $creator, $matches)) {
          $fields['author'] = $matches[1];
          $fields['epoch'] = (int)$matches[2];
        } else {
          $fields['author'] = null;
          $fields['epoch'] = null;
        }

        $commit = nonempty($fields['*objectname'], $fields['objectname']);

        $short = substr($fields['refname'], strlen($prefix));
        if ($short == 'HEAD') {
          continue;
        }

        $ref = id(new DiffusionRepositoryRef())
          ->setShortName($short)
          ->setCommitIdentifier($commit)
          ->setRawFields($fields);

        $results[] = $ref;
      }
    }

    return $results;
  }

  /**
   * List of git `--format` fields we want to grab.
   */
  private function getFields() {
    return array(
      'objectname',
      'objecttype',
      'refname',
      '*objectname',
      '*objecttype',
      'subject',
      'creator',
    );
  }

  /**
   * Get a string for `--format` which enumerates all the fields we want.
   */
  private function getFormatString() {
    $fields = $this->getFields();

    foreach ($fields as $key => $field) {
      $fields[$key] = '%('.$field.')';
    }

    return implode('%01', $fields);
  }

  /**
   * Parse a line back into fields.
   */
  private function extractFields($line) {
    $fields = $this->getFields();
    $parts = explode("\1", $line, count($fields));

    $dict = array();
    foreach ($fields as $index => $field) {
      $dict[$field] = idx($parts, $index);
    }

    return $dict;
  }

}
