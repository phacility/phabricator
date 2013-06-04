<?php

abstract class DiffusionExpandShortNameQuery extends DiffusionQuery {

  private $commit;
  private $commitType = 'commit';
  private $tagContent;
  private $repository;

  public function setCommit($commit) {
    $this->commit = $commit;
  }
  public function getCommit() {
    return $this->commit;
  }

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }
  public function getRepository() {
    return $this->repository;
  }

  protected function setCommitType($type) {
    $this->commitType = $type;
    return $this;
  }
  protected function setTagContent($content) {
    $this->tagContent = $content;
    return $this;
  }

  final public static function newFromRepository(
    PhabricatorRepository $repository) {

    $obj = parent::initQueryObject(__CLASS__, $repository);
    $obj->setRepository($repository);
    return $obj;
  }

  final public function expand() {
    $this->executeQuery();

    return array(
      'commit' => $this->commit,
      'commitType' => $this->commitType,
      'tagContent' => $this->tagContent);
  }

}
