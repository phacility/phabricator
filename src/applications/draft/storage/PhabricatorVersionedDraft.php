<?php

final class PhabricatorVersionedDraft extends PhabricatorDraftDAO {

  const KEY_VERSION = 'draft.version';

  protected $objectPHID;
  protected $authorPHID;
  protected $version;
  protected $properties = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'version' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID', 'authorPHID', 'version'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public static function loadDraft(
    $object_phid,
    $viewer_phid) {

    return id(new PhabricatorVersionedDraft())->loadOneWhere(
      'objectPHID = %s AND authorPHID = %s ORDER BY version DESC LIMIT 1',
      $object_phid,
      $viewer_phid);
  }

  public static function loadOrCreateDraft(
    $object_phid,
    $viewer_phid,
    $version) {

    $draft = self::loadDraft($object_phid, $viewer_phid);
    if ($draft) {
      return $draft;
    }

    return id(new PhabricatorVersionedDraft())
      ->setObjectPHID($object_phid)
      ->setAuthorPHID($viewer_phid)
      ->setVersion((int)$version)
      ->save();
  }

  public static function purgeDrafts(
    $object_phid,
    $viewer_phid,
    $version) {

    $draft = new PhabricatorVersionedDraft();
    $conn_w = $draft->establishConnection('w');

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE objectPHID = %s AND authorPHID = %s
        AND version <= %d',
      $draft->getTableName(),
      $object_phid,
      $viewer_phid,
      $version);
  }

}
