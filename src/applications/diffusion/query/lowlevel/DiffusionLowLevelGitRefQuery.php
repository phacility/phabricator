<?php

/**
 * Execute and parse a low-level Git ref query using `git for-each-ref`. This
 * is useful for returning a list of tags or branches.
 */
final class DiffusionLowLevelGitRefQuery extends DiffusionLowLevelQuery {

  private $refTypes;

  public function withRefTypes(array $ref_types) {
    $this->refTypes = $ref_types;
    return $this;
  }

  protected function executeQuery() {
    $type_branch = PhabricatorRepositoryRefCursor::TYPE_BRANCH;
    $type_tag = PhabricatorRepositoryRefCursor::TYPE_TAG;
    $type_ref = PhabricatorRepositoryRefCursor::TYPE_REF;

    $ref_types = $this->refTypes;
    if (!$ref_types) {
      $ref_types = array($type_branch, $type_tag, $type_ref);
    }

    $ref_types = array_fuse($ref_types);

    $with_branches = isset($ref_types[$type_branch]);
    $with_tags = isset($ref_types[$type_tag]);
    $with_refs = isset($refs_types[$type_ref]);

    $repository = $this->getRepository();

    $prefixes = array();

    if ($repository->isWorkingCopyBare()) {
      $branch_prefix = 'refs/heads/';
    } else {
      $remote = DiffusionGitBranch::DEFAULT_GIT_REMOTE;
      $branch_prefix = 'refs/remotes/'.$remote.'/';
    }

    $tag_prefix = 'refs/tags/';


    if ($with_refs || count($ref_types) > 1) {
      // If we're loading refs or more than one type of ref, just query
      // everything.
      $prefix = 'refs/';
    } else {
      if ($with_branches) {
        $prefix = $branch_prefix;
      }
      if ($with_tags) {
        $prefix = $tag_prefix;
      }
    }

    $branch_len = strlen($branch_prefix);
    $tag_len = strlen($tag_prefix);

    list($stdout) = $repository->execxLocalCommand(
      'for-each-ref --sort=%s --format=%s -- %s',
      '-creatordate',
      $this->getFormatString(),
      $prefix);

    $stdout = rtrim($stdout);
    if (!strlen($stdout)) {
      return array();
    }

    $remote_prefix = 'refs/remotes/';
    $remote_len = strlen($remote_prefix);

    // NOTE: Although git supports --count, we can't apply any offset or
    // limit logic until the very end because we may encounter a HEAD which
    // we want to discard.

    $lines = explode("\n", $stdout);
    $results = array();
    foreach ($lines as $line) {
      $fields = $this->extractFields($line);

      $refname = $fields['refname'];
      if (!strncmp($refname, $branch_prefix, $branch_len)) {
        $short = substr($refname, $branch_len);
        $type = $type_branch;
      } else if (!strncmp($refname, $tag_prefix, $tag_len)) {
        $short = substr($refname, $tag_len);
        $type = $type_tag;
      } else if (!strncmp($refname, $remote_prefix, $remote_len)) {
        // If we've found a remote ref that we didn't recognize as naming a
        // branch, just ignore it. This can happen if we're observing a remote,
        // and that remote has its own remotes. We don't care about their
        // state and they may be out of date, so ignore them.
        continue;
      } else {
        $short = $refname;
        $type = $type_ref;
      }

      // If this isn't a type of ref we care about, skip it.
      if (empty($ref_types[$type])) {
        continue;
      }

      // If this is the local HEAD, skip it.
      if ($short == 'HEAD') {
        continue;
      }

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

      $ref = id(new DiffusionRepositoryRef())
        ->setRefType($type)
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
