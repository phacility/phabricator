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
        ->setHeadCommitIdentifier($dict['head_commit_identifier']);
    }
    return $branches;
  }

  public function toDictionary() {
    return array(
      'name' => $this->getName(),
      'head_commit_identifier' => $this->getHeadCommitIdentifier()
    );
  }

}
