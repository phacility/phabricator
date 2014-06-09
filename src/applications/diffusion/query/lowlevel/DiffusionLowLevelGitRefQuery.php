<?php

/**
 * Execute and parse a low-level Git ref query using `git for-each-ref`. This
 * is useful for returning a list of tags or branches.
 *
 *
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

    if ($this->isTag && $this->isOriginBranch) {
      throw new Exception('Specify tags or origin branches, not both!');
    } else if ($this->isTag) {
      $prefix = 'refs/tags/';
    } else if ($this->isOriginBranch) {
      if ($repository->isWorkingCopyBare()) {
        $prefix = 'refs/heads/';
      } else {
        $remote = DiffusionGitBranch::DEFAULT_GIT_REMOTE;
        $prefix = 'refs/remotes/'.$remote.'/';
      }
    } else {
      throw new Exception('Specify tags or origin branches!');
    }

    $order = '-creatordate';

    list($stdout) = $repository->execxLocalCommand(
      'for-each-ref --sort=%s --format=%s %s',
      $order,
      $this->getFormatString(),
      $prefix);

    $stdout = rtrim($stdout);
    if (!strlen($stdout)) {
      return array();
    }

    // NOTE: Although git supports --count, we can't apply any offset or limit
    // logic until the very end because we may encounter a HEAD which we want
    // to discard.

    $lines = explode("\n", $stdout);
    $results = array();
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
