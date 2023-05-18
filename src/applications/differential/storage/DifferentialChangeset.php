<?php

final class DifferentialChangeset
  extends DifferentialDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface,
    PhabricatorConduitResultInterface {

  protected $diffID;
  protected $oldFile;
  protected $filename;
  protected $awayPaths;
  protected $changeType;
  protected $fileType;
  protected $metadata = array();
  protected $oldProperties;
  protected $newProperties;
  protected $addLines;
  protected $delLines;

  private $unsavedHunks = array();
  private $hunks = self::ATTACHABLE;
  private $diff = self::ATTACHABLE;

  private $authorityPackages;
  private $changesetPackages;

  private $newFileObject = self::ATTACHABLE;
  private $oldFileObject = self::ATTACHABLE;

  private $hasOldState;
  private $hasNewState;
  private $oldStateMetadata;
  private $newStateMetadata;
  private $oldFileType;
  private $newFileType;

  const TABLE_CACHE = 'differential_changeset_parse_cache';

  const METADATA_TRUSTED_ATTRIBUTES = 'attributes.trusted';
  const METADATA_UNTRUSTED_ATTRIBUTES = 'attributes.untrusted';
  const METADATA_EFFECT_HASH = 'hash.effect';

  const ATTRIBUTE_GENERATED = 'generated';

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
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

  public function getPHIDType() {
    return DifferentialChangesetPHIDType::TYPECONST;
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

  public function setAuthorityPackages(array $authority_packages) {
    $this->authorityPackages = mpull($authority_packages, null, 'getPHID');
    return $this;
  }

  public function getAuthorityPackages() {
    return $this->authorityPackages;
  }

  public function setChangesetPackages($changeset_packages) {
    $this->changesetPackages = mpull($changeset_packages, null, 'getPHID');
    return $this;
  }

  public function getChangesetPackages() {
    return $this->changesetPackages;
  }

  public function setHasOldState($has_old_state) {
    $this->hasOldState = $has_old_state;
    return $this;
  }

  public function setHasNewState($has_new_state) {
    $this->hasNewState = $has_new_state;
    return $this;
  }

  public function hasOldState() {
    if ($this->hasOldState !== null) {
      return $this->hasOldState;
    }

    $change_type = $this->getChangeType();
    return !DifferentialChangeType::isCreateChangeType($change_type);
  }

  public function hasNewState() {
    if ($this->hasNewState !== null) {
      return $this->hasNewState;
    }

    $change_type = $this->getChangeType();
    return !DifferentialChangeType::isDeleteChangeType($change_type);
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

      $hunks = id(new DifferentialHunk())->loadAllWhere(
        'changesetID = %d',
        $this->getID());
      foreach ($hunks as $hunk) {
        $hunk->delete();
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

  /**
   * Test if this changeset and some other changeset put the affected file in
   * the same state.
   *
   * @param DifferentialChangeset Changeset to compare against.
   * @return bool True if the two changesets have the same effect.
   */
  public function hasSameEffectAs(DifferentialChangeset $other) {
    if ($this->getFilename() !== $other->getFilename()) {
      return false;
    }

    $hash_key = self::METADATA_EFFECT_HASH;

    $u_hash = $this->getChangesetMetadata($hash_key);
    if ($u_hash === null) {
      return false;
    }

    $v_hash = $other->getChangesetMetadata($hash_key);
    if ($v_hash === null) {
      return false;
    }

    if ($u_hash !== $v_hash) {
      return false;
    }

    // Make sure the final states for the file properties (like the "+x"
    // executable bit) match one another.
    $u_props = $this->getNewProperties();
    $v_props = $other->getNewProperties();
    ksort($u_props);
    ksort($v_props);

    if ($u_props !== $v_props) {
      return false;
    }

    return true;
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
    return 'change-'.PhabricatorHash::digestForAnchor($this->getFilename());
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

  public function attachDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function getDiff() {
    return $this->assertAttached($this->diff);
  }

  public function getOldStatePathVector() {
    $path = $this->getOldFile();
    if ($path === null || !strlen($path)) {
      $path = $this->getFilename();
    }

    $path = trim($path, '/');
    $path = explode('/', $path);

    return $path;
  }

  public function getNewStatePathVector() {
    if (!$this->hasNewState()) {
      return null;
    }

    $path = $this->getFilename();
    $path = trim($path, '/');
    $path = explode('/', $path);

    return $path;
  }

  public function newFileTreeIcon() {
    $icon = $this->getPathIconIcon();
    $color = $this->getPathIconColor();

    return id(new PHUIIconView())
      ->setIcon("{$icon} {$color}");
  }

  public function getIsOwnedChangeset() {
    $authority_packages = $this->getAuthorityPackages();
    $changeset_packages = $this->getChangesetPackages();

    if (!$authority_packages || !$changeset_packages) {
      return false;
    }

    return (bool)array_intersect_key($authority_packages, $changeset_packages);
  }

  public function getIsLowImportanceChangeset() {
    if (!$this->hasNewState()) {
      return true;
    }

    if ($this->isGeneratedChangeset()) {
      return true;
    }

    return false;
  }

  public function getPathIconIcon() {
    return idx($this->getPathIconDetails(), 'icon');
  }

  public function getPathIconColor() {
    return idx($this->getPathIconDetails(), 'color');
  }

  private function getPathIconDetails() {
    $change_icons = array(
      DifferentialChangeType::TYPE_DELETE => array(
        'icon' => 'fa-times',
        'color' => 'delete-color',
      ),
      DifferentialChangeType::TYPE_ADD => array(
        'icon' => 'fa-plus',
        'color' => 'create-color',
      ),
      DifferentialChangeType::TYPE_MOVE_AWAY => array(
        'icon' => 'fa-circle-o',
        'color' => 'grey',
      ),
      DifferentialChangeType::TYPE_MULTICOPY => array(
        'icon' => 'fa-circle-o',
        'color' => 'grey',
      ),
      DifferentialChangeType::TYPE_MOVE_HERE => array(
        'icon' => 'fa-plus-circle',
        'color' => 'create-color',
      ),
      DifferentialChangeType::TYPE_COPY_HERE => array(
        'icon' => 'fa-plus-circle',
        'color' => 'create-color',
      ),
    );

    $change_type = $this->getChangeType();
    if (isset($change_icons[$change_type])) {
      return $change_icons[$change_type];
    }

    if ($this->isGeneratedChangeset()) {
      return array(
        'icon' => 'fa-cogs',
        'color' => 'grey',
      );
    }

    $file_type = $this->getFileType();
    $icon = DifferentialChangeType::getIconForFileType($file_type);

    return array(
      'icon' => $icon,
      'color' => 'bluetext',
    );
  }

  public function setChangesetMetadata($key, $value) {
    if (!is_array($this->metadata)) {
      $this->metadata = array();
    }

    $this->metadata[$key] = $value;

    return $this;
  }

  public function getChangesetMetadata($key, $default = null) {
    if (!is_array($this->metadata)) {
      return $default;
    }

    return idx($this->metadata, $key, $default);
  }

  private function setInternalChangesetAttribute($trusted, $key, $value) {
    if ($trusted) {
      $meta_key = self::METADATA_TRUSTED_ATTRIBUTES;
    } else {
      $meta_key = self::METADATA_UNTRUSTED_ATTRIBUTES;
    }

    $attributes = $this->getChangesetMetadata($meta_key, array());
    $attributes[$key] = $value;
    $this->setChangesetMetadata($meta_key, $attributes);

    return $this;
  }

  private function getInternalChangesetAttributes($trusted) {
    if ($trusted) {
      $meta_key = self::METADATA_TRUSTED_ATTRIBUTES;
    } else {
      $meta_key = self::METADATA_UNTRUSTED_ATTRIBUTES;
    }

    return $this->getChangesetMetadata($meta_key, array());
  }

  public function setTrustedChangesetAttribute($key, $value) {
    return $this->setInternalChangesetAttribute(true, $key, $value);
  }

  public function getTrustedChangesetAttributes() {
    return $this->getInternalChangesetAttributes(true);
  }

  public function getTrustedChangesetAttribute($key, $default = null) {
    $map = $this->getTrustedChangesetAttributes();
    return idx($map, $key, $default);
  }

  public function setUntrustedChangesetAttribute($key, $value) {
    return $this->setInternalChangesetAttribute(false, $key, $value);
  }

  public function getUntrustedChangesetAttributes() {
    return $this->getInternalChangesetAttributes(false);
  }

  public function getUntrustedChangesetAttribute($key, $default = null) {
    $map = $this->getUntrustedChangesetAttributes();
    return idx($map, $key, $default);
  }

  public function getChangesetAttributes() {
    // Prefer trusted values over untrusted values when both exist.
    return
      $this->getTrustedChangesetAttributes() +
      $this->getUntrustedChangesetAttributes();
  }

  public function getChangesetAttribute($key, $default = null) {
    $map = $this->getChangesetAttributes();
    return idx($map, $key, $default);
  }

  public function isGeneratedChangeset() {
    return $this->getChangesetAttribute(self::ATTRIBUTE_GENERATED);
  }

  public function getNewFileObjectPHID() {
    $metadata = $this->getMetadata();
    return idx($metadata, 'new:binary-phid');
  }

  public function getOldFileObjectPHID() {
    $metadata = $this->getMetadata();
    return idx($metadata, 'old:binary-phid');
  }

  public function attachNewFileObject(PhabricatorFile $file) {
    $this->newFileObject = $file;
    return $this;
  }

  public function getNewFileObject() {
    return $this->assertAttached($this->newFileObject);
  }

  public function attachOldFileObject(PhabricatorFile $file) {
    $this->oldFileObject = $file;
    return $this;
  }

  public function getOldFileObject() {
    return $this->assertAttached($this->oldFileObject);
  }

  public function newComparisonChangeset(
    DifferentialChangeset $against = null) {

    $left = $this;
    $right = $against;

    $left_data = $left->makeNewFile();
    $left_properties = $left->getNewProperties();
    $left_metadata = $left->getNewStateMetadata();
    $left_state = $left->hasNewState();
    $shared_metadata = $left->getMetadata();
    $left_type = $left->getNewFileType();
    if ($right) {
      $right_data = $right->makeNewFile();
      $right_properties = $right->getNewProperties();
      $right_metadata = $right->getNewStateMetadata();
      $right_state = $right->hasNewState();
      $shared_metadata = $right->getMetadata();
      $right_type = $right->getNewFileType();

      $file_name = $right->getFilename();
    } else {
      $right_data = $left->makeOldFile();
      $right_properties = $left->getOldProperties();
      $right_metadata = $left->getOldStateMetadata();
      $right_state = $left->hasOldState();
      $right_type = $left->getOldFileType();

      $file_name = $left->getFilename();
    }

    $engine = new PhabricatorDifferenceEngine();

    $synthetic = $engine->generateChangesetFromFileContent(
      $left_data,
      $right_data);

    $comparison = id(new self())
      ->makeEphemeral(true)
      ->attachDiff($left->getDiff())
      ->setOldFile($left->getFilename())
      ->setFilename($file_name);

    // TODO: Change type?
    // TODO: Away paths?
    // TODO: View state key?

    $comparison->attachHunks($synthetic->getHunks());

    $comparison->setOldProperties($left_properties);
    $comparison->setNewProperties($right_properties);

    $comparison
      ->setOldStateMetadata($left_metadata)
      ->setNewStateMetadata($right_metadata)
      ->setHasOldState($left_state)
      ->setHasNewState($right_state)
      ->setOldFileType($left_type)
      ->setNewFileType($right_type);

    // NOTE: Some metadata is not stored statefully, like the "generated"
    // flag. For now, use the rightmost "new state" metadata to fill in these
    // values.

    $metadata = $comparison->getMetadata();
    $metadata = $metadata + $shared_metadata;
    $comparison->setMetadata($metadata);

    return $comparison;
  }


  public function setNewFileType($new_file_type) {
    $this->newFileType = $new_file_type;
    return $this;
  }

  public function getNewFileType() {
    if ($this->newFileType !== null) {
      return $this->newFileType;
    }

    return $this->getFiletype();
  }

  public function setOldFileType($old_file_type) {
    $this->oldFileType = $old_file_type;
    return $this;
  }

  public function getOldFileType() {
    if ($this->oldFileType !== null) {
      return $this->oldFileType;
    }

    return $this->getFileType();
  }

  public function hasSourceTextBody() {
    $type_map = array(
      DifferentialChangeType::FILE_TEXT => true,
      DifferentialChangeType::FILE_SYMLINK => true,
    );

    $old_body = isset($type_map[$this->getOldFileType()]);
    $new_body = isset($type_map[$this->getNewFileType()]);

    return ($old_body || $new_body);
  }

  public function getNewStateMetadata() {
    return $this->getMetadataWithPrefix('new:');
  }

  public function setNewStateMetadata(array $metadata) {
    return $this->setMetadataWithPrefix($metadata, 'new:');
  }

  public function getOldStateMetadata() {
    return $this->getMetadataWithPrefix('old:');
  }

  public function setOldStateMetadata(array $metadata) {
    return $this->setMetadataWithPrefix($metadata, 'old:');
  }

  private function getMetadataWithPrefix($prefix) {
    $length = strlen($prefix);

    $result = array();
    foreach ($this->getMetadata() as $key => $value) {
      if (strncmp($key, $prefix, $length)) {
        continue;
      }

      $key = substr($key, $length);
      $result[$key] = $value;
    }

    return $result;
  }

  private function setMetadataWithPrefix(array $metadata, $prefix) {
    foreach ($metadata as $key => $value) {
      $key = $prefix.$key;
      $this->metadata[$key] = $value;
    }

    return $this;
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


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->openTransaction();

      $hunks = id(new DifferentialHunk())->loadAllWhere(
        'changesetID = %d',
        $this->getID());
      foreach ($hunks as $hunk) {
        $engine->destroyObject($hunk);
      }

      $this->delete();

    $this->saveTransaction();
  }

/* -(  PhabricatorConduitResultInterface  )---------------------------------- */

  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('diffPHID')
        ->setType('phid')
        ->setDescription(pht('The diff the changeset is attached to.')),
    );
  }

  public function getFieldValuesForConduit() {
    $diff = $this->getDiff();

    $repository = null;
    if ($diff) {
      $revision = $diff->getRevision();
      if ($revision) {
        $repository = $revision->getRepository();
      }
    }

    $absolute_path = $this->getAbsoluteRepositoryPath($repository, $diff);
    if (strlen($absolute_path)) {
      $absolute_path = base64_encode($absolute_path);
    } else {
      $absolute_path = null;
    }

    $display_path = $this->getDisplayFilename();

    return array(
      'diffPHID' => $diff->getPHID(),
      'path' => array(
        'displayPath' => $display_path,
        'absolutePath.base64' => $absolute_path,
      ),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }


}
