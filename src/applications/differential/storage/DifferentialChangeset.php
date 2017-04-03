<?php

final class DifferentialChangeset extends DifferentialDAO
  implements PhabricatorPolicyInterface {

  protected $diffID;
  protected $oldFile;
  protected $filename;
  protected $awayPaths;
  protected $changeType;
  protected $fileType;
  protected $metadata;
  protected $oldProperties;
  protected $newProperties;
  protected $addLines;
  protected $delLines;

  private $unsavedHunks = array();
  private $hunks = self::ATTACHABLE;
  private $diff = self::ATTACHABLE;

  const TABLE_CACHE = 'differential_changeset_parse_cache';

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'metadata'      => self::SERIALIZATION_JSON,
        'oldProperties' => self::SERIALIZATION_JSON,
        'newProperties' => self::SERIALIZATION_JSON,
        'awayPaths'     => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'oldFile' => 'bytes?',
        'filename' => 'bytes',
        'changeType' => 'uint32',
        'fileType' => 'uint32',
        'addLines' => 'uint32',
        'delLines' => 'uint32',

        // T6203/NULLABILITY
        // These should all be non-nullable, and store reasonable default
        // JSON values if empty.
        'awayPaths' => 'text?',
        'metadata' => 'text?',
        'oldProperties' => 'text?',
        'newProperties' => 'text?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'diffID' => array(
          'columns' => array('diffID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getAffectedLineCount() {
    return $this->getAddLines() + $this->getDelLines();
  }

  public function attachHunks(array $hunks) {
    assert_instances_of($hunks, 'DifferentialHunk');
    $this->hunks = $hunks;
    return $this;
  }

  public function getHunks() {
    return $this->assertAttached($this->hunks);
  }

  public function getDisplayFilename() {
    $name = $this->getFilename();
    if ($this->getFileType() == DifferentialChangeType::FILE_DIRECTORY) {
      $name .= '/';
    }
    return $name;
  }

  public function getOwnersFilename() {
    // TODO: For Subversion, we should adjust these paths to be relative to
    // the repository root where possible.

    $path = $this->getFilename();

    if (!isset($path[0])) {
      return '/';
    }

    if ($path[0] != '/') {
      $path = '/'.$path;
    }

    return $path;
  }

  public function addUnsavedHunk(DifferentialHunk $hunk) {
    if ($this->hunks === self::ATTACHABLE) {
      $this->hunks = array();
    }
    $this->hunks[] = $hunk;
    $this->unsavedHunks[] = $hunk;
    return $this;
  }

  public function save() {
    $this->openTransaction();
      $ret = parent::save();
      foreach ($this->unsavedHunks as $hunk) {
        $hunk->setChangesetID($this->getID());
        $hunk->save();
      }
    $this->saveTransaction();
    return $ret;
  }

  public function delete() {
    $this->openTransaction();

      $modern_hunks = id(new DifferentialModernHunk())->loadAllWhere(
        'changesetID = %d',
        $this->getID());
      foreach ($modern_hunks as $modern_hunk) {
        $modern_hunk->delete();
      }

      $this->unsavedHunks = array();

      queryfx(
        $this->establishConnection('w'),
        'DELETE FROM %T WHERE id = %d',
        self::TABLE_CACHE,
        $this->getID());

      $ret = parent::delete();
    $this->saveTransaction();
    return $ret;
  }

  public function getSortKey() {
    $sort_key = $this->getFilename();
    // Sort files with ".h" in them first, so headers (.h, .hpp) come before
    // implementations (.c, .cpp, .cs).
    $sort_key = str_replace('.h', '.!h', $sort_key);
    return $sort_key;
  }

  public function makeNewFile() {
    $file = mpull($this->getHunks(), 'makeNewFile');
    return implode('', $file);
  }

  public function makeOldFile() {
    $file = mpull($this->getHunks(), 'makeOldFile');
    return implode('', $file);
  }

  public function makeChangesWithContext($num_lines = 3) {
    $with_context = array();
    foreach ($this->getHunks() as $hunk) {
      $context = array();
      $changes = explode("\n", $hunk->getChanges());
      foreach ($changes as $l => $line) {
        $type = substr($line, 0, 1);
        if ($type == '+' || $type == '-') {
          $context += array_fill($l - $num_lines, 2 * $num_lines + 1, true);
        }
      }
      $with_context[] = array_intersect_key($changes, $context);
    }
    return array_mergev($with_context);
  }

  public function getAnchorName() {
    return substr(md5($this->getFilename()), 0, 8);
  }

  public function getAbsoluteRepositoryPath(
    PhabricatorRepository $repository = null,
    DifferentialDiff $diff = null) {

    $base = '/';
    if ($diff && $diff->getSourceControlPath()) {
      $base = id(new PhutilURI($diff->getSourceControlPath()))->getPath();
    }

    $path = $this->getFilename();
    $path = rtrim($base, '/').'/'.ltrim($path, '/');

    $svn = PhabricatorRepositoryType::REPOSITORY_TYPE_SVN;
    if ($repository && $repository->getVersionControlSystem() == $svn) {
      $prefix = $repository->getDetail('remote-uri');
      $prefix = id(new PhutilURI($prefix))->getPath();
      if (!strncmp($path, $prefix, strlen($prefix))) {
        $path = substr($path, strlen($prefix));
      }
      $path = '/'.ltrim($path, '/');
    }

    return $path;
  }

  public function getWhitespaceMatters() {
    $config = PhabricatorEnv::getEnvConfig('differential.whitespace-matters');
    foreach ($config as $regexp) {
      if (preg_match($regexp, $this->getFilename())) {
        return true;
      }
    }

    return false;
  }

  public function attachDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function getDiff() {
    return $this->assertAttached($this->diff);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getDiff()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getDiff()->hasAutomaticCapability($capability, $viewer);
  }

}
