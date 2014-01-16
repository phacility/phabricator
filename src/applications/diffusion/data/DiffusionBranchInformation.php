<?php

final class DiffusionBranchInformation {

  const DEFAULT_GIT_REMOTE = 'origin';

  private $name;
  private $headCommitIdentifier;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setHeadCommitIdentifier($head_commit_identifier) {
    $this->headCommitIdentifier = $head_commit_identifier;
    return $this;
  }

  public function getHeadCommitIdentifier() {
    return $this->headCommitIdentifier;
  }

  public static function newFromConduit(array $dicts) {
    $branches = array();
    foreach ($dicts as $dict) {
      $branches[] = id(new DiffusionBranchInformation())
        ->setName($dict['name'])
        ->setHeadCommitIdentifier($dict['headCommitIdentifier']);
    }
    return $branches;
  }

  public function toDictionary() {
    return array(
      'name' => $this->getName(),
      'headCommitIdentifier' => $this->getHeadCommitIdentifier()
    );
  }

  // TODO: These are hacks to make this compatible with DiffusionRepositoryRef
  // for PhabricatorRepositoryRefEngine. The two classes should be merged.

  public function getShortName() {
    return $this->getName();
  }

  public function getCommitIdentifier() {
    return $this->getHeadCommitIdentifier();
  }

}
