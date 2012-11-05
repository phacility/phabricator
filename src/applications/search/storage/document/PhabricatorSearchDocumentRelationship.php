<?php

/**
 * @group search
 */
final class PhabricatorSearchDocumentRelationship extends PhabricatorSearchDAO {

  protected $phid;
  protected $relatedPHID;
  protected $relation;
  protected $relatedType;
  protected $relatedTime;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_IDS        => self::IDS_MANUAL,
    ) + parent::getConfiguration();
  }

}
