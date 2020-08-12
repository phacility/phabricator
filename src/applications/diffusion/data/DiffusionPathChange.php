<?php

final class DiffusionPathChange extends Phobject {

  private $path;
  private $commitIdentifier;
  private $commit;
  private $commitData;

  private $changeType;
  private $fileType;
  private $targetPath;
  private $targetCommitIdentifier;
  private $awayPaths = array();

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getPath() {
    return $this->path;
  }

  public function setChangeType($change_type) {
    $this->changeType = $change_type;
    return $this;
  }

  public function getChangeType() {
    return $this->changeType;
  }

  public function setFileType($file_type) {
    $this->fileType = $file_type;
    return $this;
  }

  public function getFileType() {
    return $this->fileType;
  }

  public function setTargetPath($target_path) {
    $this->targetPath = $target_path;
    return $this;
  }

  public function getTargetPath() {
    return $this->targetPath;
  }

  public function setAwayPaths(array $away_paths) {
    $this->awayPaths = $away_paths;
    return $this;
  }

  public function getAwayPaths() {
    return $this->awayPaths;
  }

  public function setCommitIdentifier($commit) {
    $this->commitIdentifier = $commit;
    return $this;
  }

  public function getCommitIdentifier() {
    return $this->commitIdentifier;
  }

  public function setTargetCommitIdentifier($target_commit_identifier) {
    $this->targetCommitIdentifier = $target_commit_identifier;
    return $this;
  }

  public function getTargetCommitIdentifier() {
    return $this->targetCommitIdentifier;
  }

  public function setCommit($commit) {
    $this->commit = $commit;
    return $this;
  }

  public function getCommit() {
    return $this->commit;
  }

  public function setCommitData($commit_data) {
    $this->commitData = $commit_data;
    return $this;
  }

  public function getCommitData() {
    return $this->commitData;
  }


  public function getEpoch() {
    if ($this->getCommit()) {
      return $this->getCommit()->getEpoch();
    }
    return null;
  }

  public function getAuthorName() {
    if ($this->getCommitData()) {
      return $this->getCommitData()->getAuthorString();
    }
    return null;
  }

  public function getSummary() {
    if (!$this->getCommitData()) {
      return null;
    }
    return $this->getCommitData()->getSummary();
  }

  public static function convertToArcanistChanges(array $changes) {
    assert_instances_of($changes, __CLASS__);
    $direct = array();
    $result = array();
    foreach ($changes as $path) {
      $change = new ArcanistDiffChange();
      $change->setCurrentPath($path->getPath());
      $direct[] = $path->getPath();
      $change->setType($path->getChangeType());
      $file_type = $path->getFileType();
      if ($file_type == DifferentialChangeType::FILE_NORMAL) {
        $file_type = DifferentialChangeType::FILE_TEXT;
      }
      $change->setFileType($file_type);
      $change->setOldPath($path->getTargetPath());
      foreach ($path->getAwayPaths() as $away_path) {
        $change->addAwayPath($away_path);
      }
      $result[$path->getPath()] = $change;
    }

    return array_select_keys($result, $direct);
  }

  public static function convertToDifferentialChangesets(
    PhabricatorUser $user,
    array $changes) {
    assert_instances_of($changes, __CLASS__);
    $arcanist_changes = self::convertToArcanistChanges($changes);
    $diff = DifferentialDiff::newEphemeralFromRawChanges(
      $arcanist_changes);
    return $diff->getChangesets();
  }

  public function toDictionary() {
    $commit = $this->getCommit();
    if ($commit) {
      $commit_dict = $commit->toDictionary();
    } else {
      $commit_dict = array();
    }
    $commit_data = $this->getCommitData();
    if ($commit_data) {
      $commit_data_dict = $commit_data->toDictionary();
    } else {
      $commit_data_dict = array();
    }
    return array(
      'path' => $this->getPath(),
      'commitIdentifier' => $this->getCommitIdentifier(),
      'commit' => $commit_dict,
      'commitData' => $commit_data_dict,
      'fileType' => $this->getFileType(),
      'changeType' => $this->getChangeType(),
      'targetPath' =>  $this->getTargetPath(),
      'targetCommitIdentifier' => $this->getTargetCommitIdentifier(),
      'awayPaths' => $this->getAwayPaths(),
    );
  }

  public static function newFromConduit(array $dicts) {
    $results = array();
    foreach ($dicts as $dict) {
      $commit = PhabricatorRepositoryCommit::newFromDictionary($dict['commit']);
      $commit_data =
        PhabricatorRepositoryCommitData::newFromDictionary(
          $dict['commitData']);
      $results[] = id(new DiffusionPathChange())
        ->setPath($dict['path'])
        ->setCommitIdentifier($dict['commitIdentifier'])
        ->setCommit($commit)
        ->setCommitData($commit_data)
        ->setFileType($dict['fileType'])
        ->setChangeType($dict['changeType'])
        ->setTargetPath($dict['targetPath'])
        ->setTargetCommitIdentifier($dict['targetCommitIdentifier'])
        ->setAwayPaths($dict['awayPaths']);
    }
    return $results;
  }

}
