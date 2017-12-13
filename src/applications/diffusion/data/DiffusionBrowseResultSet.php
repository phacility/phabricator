<?php

final class DiffusionBrowseResultSet extends Phobject {

  const REASON_IS_FILE              = 'is-file';
  const REASON_IS_SUBMODULE         = 'is-submodule';
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

  public function toDictionary() {
    $paths = $this->getPathDicts();

    return array(
      'paths' => $paths,
      'isValidResults' => $this->isValidResults(),
      'reasonForEmptyResultSet' => $this->getReasonForEmptyResultSet(),
      'existedAtCommit' => $this->getExistedAtCommit(),
      'deletedAtCommit' => $this->getDeletedAtCommit(),
    );
  }

  public function getPathDicts() {
    $paths = $this->getPaths();
    if ($paths) {
      return mpull($paths, 'toDictionary');
    }
    return array();
  }

  /**
   * Get the best README file in this result set, if one exists.
   *
   * Callers should normally use `diffusion.filecontentquery` to pull README
   * content.
   *
   * @return string|null Full path to best README, or null if one does not
   *   exist.
   */
  public function getReadmePath() {
    $allowed_types = array(
      ArcanistDiffChangeType::FILE_NORMAL => true,
      ArcanistDiffChangeType::FILE_TEXT => true,
    );

    $candidates = array();
    foreach ($this->getPaths() as $path_object) {
      if (empty($allowed_types[$path_object->getFileType()])) {
        // Skip directories, images, etc.
        continue;
      }

      $local_path = $path_object->getPath();
      if (!preg_match('/^readme(\.|$)/i', $local_path)) {
        // Skip files not named "README".
        continue;
      }

      $full_path = $path_object->getFullPath();
      $candidates[$full_path] = self::getReadmePriority($local_path);
    }

    if (!$candidates) {
      return null;
    }

    arsort($candidates);
    return head_key($candidates);
  }

  /**
   * Get the priority of a README file.
   *
   * When a directory contains several README files, this function scores them
   * so the caller can select a preferred file. See @{method:getReadmePath}.
   *
   * @param string Local README path, like "README.txt".
   * @return int Priority score, with higher being more preferred.
   */
  public static function getReadmePriority($path) {
    $path = phutil_utf8_strtolower($path);
    if ($path == 'readme') {
      return 90;
    }

    $ext = last(explode('.', $path));
    switch ($ext) {
      case 'remarkup':
        return 100;
      case 'rainbow':
        return 80;
      case 'md':
        return 70;
      case 'txt':
        return 60;
      default:
        return 50;
    }
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
      ->setDeletedAtCommit($data['deletedAtCommit']);
  }
}
