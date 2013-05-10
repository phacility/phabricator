<?php

final class DiffusionBrowseResultSet {

  const REASON_IS_FILE              = 'is-file';
  const REASON_IS_DELETED           = 'is-deleted';
  const REASON_IS_NONEXISTENT       = 'nonexistent';
  const REASON_BAD_COMMIT           = 'bad-commit';
  const REASON_IS_EMPTY             = 'empty';
  const REASON_IS_UNTRACKED_PARENT  = 'untracked-parent';

  private $paths;
  private $isValidResults;
  private $reasonForEmptyResultSet;
  private $existedAtCommit;
  private $deletedAtCommit;
  private $readmeContent;

  public function setPaths(array $paths) {
    assert_instances_of($paths, 'DiffusionRepositoryPath');
    $this->paths = $paths;
    return $this;
  }
  public function getPaths() {
    return $this->paths;
  }

  public function setIsValidResults($is_valid) {
    $this->isValidResults = $is_valid;
    return $this;
  }
  public function isValidResults() {
    return $this->isValidResults;
  }

  public function setReasonForEmptyResultSet($reason) {
    $this->reasonForEmptyResultSet = $reason;
    return $this;
  }
  public function getReasonForEmptyResultSet() {
    return $this->reasonForEmptyResultSet;
  }

  public function setExistedAtCommit($existed_at_commit) {
    $this->existedAtCommit = $existed_at_commit;
    return $this;
  }
  public function getExistedAtCommit() {
    return $this->existedAtCommit;
  }

  public function setDeletedAtCommit($deleted_at_commit) {
    $this->deletedAtCommit = $deleted_at_commit;
    return $this;
  }
  public function getDeletedAtCommit() {
    return $this->deletedAtCommit;
  }

  public function setReadmeContent($readme_content) {
    $this->readmeContent = $readme_content;
    return $this;
  }
  public function getReadmeContent() {
    return $this->readmeContent;
  }

  public function toDictionary() {
    $paths = $this->getPaths();
    if ($paths) {
      $paths = mpull($paths, 'toDictionary');
    }

    return array(
      'paths' => $paths,
      'isValidResults' => $this->isValidResults(),
      'reasonForEmptyResultSet' => $this->getReasonForEmptyResultSet(),
      'existedAtCommit' => $this->getExistedAtCommit(),
      'deletedAtCommit' => $this->getDeletedAtCommit(),
      'readmeContent' => $this->getReadmeContent());
  }

  public static function newFromConduit(array $data) {
    $paths = array();
    $path_dicts = $data['paths'];
    foreach ($path_dicts as $dict) {
      $paths[] = DiffusionRepositoryPath::newFromDictionary($dict);
    }
    return id(new DiffusionBrowseResultSet())
      ->setPaths($paths)
      ->setIsValidResults($data['isValidResults'])
      ->setReasonForEmptyResultSet($data['reasonForEmptyResultSet'])
      ->setExistedAtCommit($data['existedAtCommit'])
      ->setDeletedAtCommit($data['deletedAtCommit'])
      ->setReadmeContent($data['readmeContent']);
  }
}
