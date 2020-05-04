<?php

final class DifferentialViewState
  extends DifferentialDAO
  implements PhabricatorPolicyInterface {

  protected $viewerPHID;
  protected $objectPHID;
  protected $viewState = array();

  private $hasModifications;

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'viewState' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_viewer' => array(
          'columns' => array('viewerPHID', 'objectPHID'),
          'unique' => true,
        ),
        'key_object' => array(
          'columns' => array('objectPHID'),
        ),
        'key_modified' => array(
          'columns' => array('dateModified'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function setChangesetProperty(
    DifferentialChangeset $changeset,
    $key,
    $value) {

    if ($this->getChangesetProperty($changeset, $key) === $value) {
      return;
    }

    $properties = array(
      'value' => $value,
      'epoch' => PhabricatorTime::getNow(),
    );

    $diff_id = $changeset->getDiffID();
    if ($diff_id !== null) {
      $properties['diffID'] = (int)$diff_id;
    }

    $changeset_id = $changeset->getID();
    if ($changeset_id !== null) {
      $properties['changesetID'] = (int)$changeset_id;
    }

    $path_hash = $this->getChangesetPathHash($changeset);
    $changeset_phid = $this->getChangesetKey($changeset);

    $this->hasModifications = true;

    $this->viewState['changesets'][$path_hash][$key][$changeset_phid] =
      $properties;
  }

  public function getChangesetProperty(
    DifferentialChangeset $changeset,
    $key,
    $default = null) {

    $entries = $this->getChangesetPropertyEntries(
      $changeset,
      $key);

    $entries = isort($entries, 'epoch');

    $entry = last($entries);
    if (!is_array($entry)) {
      $entry = array();
    }

    return idx($entry, 'value', $default);
  }

  public function getChangesetPropertyEntries(
    DifferentialChangeset $changeset,
    $key) {
    $path_hash = $this->getChangesetPathHash($changeset);

    $entries = idxv($this->viewState, array('changesets', $path_hash, $key));
    if (!is_array($entries)) {
      $entries = array();
    }

    return $entries;
  }

  public function getHasModifications() {
    return $this->hasModifications;
  }

  private function getChangesetPathHash(DifferentialChangeset $changeset) {
    $path = $changeset->getFilename();
    return PhabricatorHash::digestForIndex($path);
  }

  private function getChangesetKey(DifferentialChangeset $changeset) {
    $key = $changeset->getID();

    if ($key === null) {
      return '*';
    }

    return (string)$key;
  }

  public static function copyViewStatesToObject($src_phid, $dst_phid) {
    $table = new self();
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'INSERT IGNORE INTO %R
          (viewerPHID, objectPHID, viewState, dateCreated, dateModified)
        SELECT viewerPHID, %s, viewState, dateCreated, dateModified
          FROM %R WHERE objectPHID = %s',
      $table,
      $dst_phid,
      $table,
      $src_phid);
  }

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() === $this->getViewerPHID());
  }

}
