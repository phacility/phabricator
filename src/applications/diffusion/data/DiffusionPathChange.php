<?php

final class DiffusionPathChange {

  private $path;
  private $commitIdentifier;
  private $commit;
  private $commitData;

  private $changeType;
  private $fileType;
  private $targetPath;
  private $targetCommitIdentifier;
  private $awayPaths = array();

  final public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  final public function getPath() {
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

  final public function setCommitIdentifier($commit) {
    $this->commitIdentifier = $commit;
    return $this;
  }

  final public function getCommitIdentifier() {
    return $this->commitIdentifier;
  }

  final public function setTargetCommitIdentifier($target_commit_identifier) {
    $this->targetCommitIdentifier = $target_commit_identifier;
    return $this;
  }

  final public function getTargetCommitIdentifier() {
    return $this->targetCommitIdentifier;
  }

  final public function setCommit($commit) {
    $this->commit = $commit;
    return $this;
  }

  final public function getCommit() {
    return $this->commit;
  }

  final public function setCommitData($commit_data) {
    $this->commitData = $commit_data;
    return $this;
  }

  final public function getCommitData() {
    return $this->commitData;
  }


  final public function getEpoch() {
    if ($this->getCommit()) {
      return $this->getCommit()->getEpoch();
    }
    return null;
  }

  final public function getAuthorName() {
    if ($this->getCommitData()) {
      return $this->getCommitData()->getAuthorName();
    }
    return null;
  }

  final public function getSummary() {
    if (!$this->getCommitData()) {
      return null;
    }
    $message = $this->getCommitData()->getCommitMessage();
    $first = idx(explode("\n", $message), 0);
    return substr($first, 0, 80);
  }

  final public static function convertToArcanistChanges(array $changes) {
    assert_instances_of($changes, 'DiffusionPathChange');
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

  final public static function convertToDifferentialChangesets(array $changes) {
    assert_instances_of($changes, 'DiffusionPathChange');
    $arcanist_changes = self::convertToArcanistChanges($changes);
    $diff = DifferentialDiff::newFromRawChanges($arcanist_changes);
    return $diff->getChangesets();
  }

}
