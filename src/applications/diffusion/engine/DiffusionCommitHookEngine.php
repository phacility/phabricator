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

  private function executeGitHook() {
    $updates = $this->parseGitUpdates($this->getStdin());

    // TODO: Do useful things.

    return 0;
  }

  private function executeSubversionHook() {

    // TODO: Do useful things here, too.

    return 0;
  }

  private function executeMercurialHook() {

    // TODO: Here, too, useful things should be done.

    return 0;
  }

  private function parseGitUpdates($stdin) {
    $updates = array();

    $lines = phutil_split_lines($stdin, $retain_endings = false);
    foreach ($lines as $line) {
      $parts = explode(' ', $line, 3);
      if (count($parts) != 3) {
        throw new Exception(pht('Expected "old new ref", got "%s".', $line));
      }
      $updates[] = array(
        'old' => $parts[0],
        'new' => $parts[1],
        'ref' => $parts[2],
      );
    }

    return $updates;
  }

}
