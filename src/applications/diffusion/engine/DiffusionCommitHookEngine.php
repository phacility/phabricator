<?php

final class DiffusionCommitHookEngine extends Phobject {

  private $viewer;
  private $repository;
  private $stdin;
  private $subversionTransaction;
  private $subversionRepository;


  public function setSubversionTransactionInfo($transaction, $repository) {
    $this->subversionTransaction = $transaction;
    $this->subversionRepository = $repository;
    return $this;
  }

  public function setStdin($stdin) {
    $this->stdin = $stdin;
    return $this;
  }

  public function getStdin() {
    return $this->stdin;
  }

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->repository;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function execute() {
    $type = $this->getRepository()->getVersionControlSystem();
    switch ($type) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $err = $this->executeGitHook();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $err = $this->executeSubversionHook();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $err = $this->executeMercurialHook();
        break;
      default:
        throw new Exception(pht('Unsupported repository type "%s"!', $type));
    }

    return $err;
  }

  /**
   * @task git
   */
  private function executeGitHook() {
    $updates = $this->parseGitUpdates($this->getStdin());

    // TODO: Do cheap checks: non-ff commits, mutating refs without access,
    // creating or deleting things you can't touch. We can do all non-content
    // checks here.

    $updates = $this->findGitNewCommits($updates);

    // TODO: Now, do content checks.

    return 0;
  }


  /**
   * @task git
   */
  private function parseGitUpdates($stdin) {
    $updates = array();

    $lines = phutil_split_lines($stdin, $retain_endings = false);
    foreach ($lines as $line) {
      $parts = explode(' ', $line, 3);
      if (count($parts) != 3) {
        throw new Exception(pht('Expected "old new ref", got "%s".', $line));
      }
      $update = array(
        'old' => $parts[0],
        'old.short' => substr($parts[0], 0, 8),
        'new' => $parts[1],
        'new.short' => substr($parts[1], 0, 8),
        'ref' => $parts[2],
      );

      if (preg_match('(^refs/heads/)', $update['ref'])) {
        $update['type'] = 'branch';
      } else if (preg_match('(^refs/tags/)', $update['ref'])) {
        $update['type'] = 'tag';
      } else {
        $update['type'] = 'unknown';
      }

      $updates[] = $update;
    }

    $updates = $this->findGitMergeBases($updates);

    return $updates;
  }


  /**
   * @task git
   */
  private function findGitMergeBases(array $updates) {
    $empty = str_repeat('0', 40);

    $futures = array();
    foreach ($updates as $key => $update) {
      // Updates are in the form:
      //
      //   <old hash> <new hash> <ref>
      //
      // If the old hash is "00000...", the ref is being created (either a new
      // branch, or a new tag). If the new hash is "00000...", the ref is being
      // deleted. If both are nonempty, the ref is being updated. For updates,
      // we'll figure out the `merge-base` of the old and new objects here. This
      // lets us reject non-FF changes cheaply; later, we'll figure out exactly
      // which commits are new.

      if ($update['old'] == $empty) {
        $updates[$key]['operation'] = 'create';
      } else if ($update['new'] == $empty) {
        $updates[$key]['operation'] = 'delete';
      } else {
        $updates[$key]['operation'] = 'update';
        $futures[$key] = $this->getRepository()->getLocalCommandFuture(
          'merge-base %s %s',
          $update['old'],
          $update['new']);
      }
    }

    foreach (Futures($futures)->limit(8) as $key => $future) {
      list($stdout) = $future->resolvex();
      $updates[$key]['merge-base'] = rtrim($stdout, "\n");
    }

    return $updates;
  }

  private function findGitNewCommits(array $updates) {
    $futures = array();
    foreach ($updates as $key => $update) {
      if ($update['type'] == 'delete') {
        // Deleting a branch or tag can never create any new commits.
        continue;
      }

      // NOTE: This piece of magic finds all new commits, by walking backward
      // from the new value to the value of *any* existing ref in the
      // repository. Particularly, this will cover the cases of a new branch, a
      // completely moved tag, etc.
      $futures[$key] = $this->getRepository()->getLocalCommandFuture(
        'log --format=%s %s --not --all',
        '%H',
        $update['new']);
    }

    foreach (Futures($futures)->limit(8) as $key => $future) {
      list($stdout) = $future->resolvex();
      $commits = phutil_split_lines($stdout, $retain_newlines = false);
      $updates[$key]['commits'] = $commits;
    }

    return $updates;
  }

  private function executeSubversionHook() {

    // TODO: Do useful things here, too.

    return 0;
  }

  private function executeMercurialHook() {

    // TODO: Here, too, useful things should be done.

    return 0;
  }
}
