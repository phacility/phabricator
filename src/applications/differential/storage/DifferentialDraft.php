<?php

final class DifferentialDraft extends DifferentialDAO {

  protected $objectPHID;
  protected $authorPHID;
  protected $draftKey;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'draftKey' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_unique' => array(
          'columns' => array('objectPHID', 'authorPHID', 'draftKey'),
          'unique' => true,
        ),
      ),
    )  + parent::getConfiguration();
  }

  public static function markHasDraft(
    $author_phid,
    $object_phid,
    $draft_key) {
    try {
      id(new DifferentialDraft())
        ->setObjectPHID($object_phid)
        ->setAuthorPHID($author_phid)
        ->setDraftKey($draft_key)
        ->save();
    } catch (AphrontDuplicateKeyQueryException $ex) {
      // no worries
    }
  }

  public static function deleteHasDraft(
    $author_phid,
    $object_phid,
    $draft_key) {
    $draft = id(new DifferentialDraft())->loadOneWhere(
      'objectPHID = %s AND authorPHID = %s AND draftKey = %s',
      $object_phid,
      $author_phid,
      $draft_key);
    if ($draft) {
      $draft->delete();
    }
  }

  public static function deleteAllDrafts(
    $author_phid,
    $object_phid) {

    $drafts = id(new DifferentialDraft())->loadAllWhere(
      'objectPHID = %s AND authorPHID = %s',
      $object_phid,
      $author_phid);
    foreach ($drafts as $draft) {
      $draft->delete();
    }
  }

}
