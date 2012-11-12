<?php

final class PhabricatorAuditComment extends PhabricatorAuditDAO
  implements PhabricatorMarkupInterface {

  const METADATA_ADDED_AUDITORS  = 'added-auditors';
  const METADATA_ADDED_CCS       = 'added-ccs';

  const MARKUP_FIELD_BODY        = 'markup:body';

  protected $phid;
  protected $actorPHID;
  protected $targetPHID;
  protected $action;
  protected $content;
  protected $metadata = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID('ACMT');
  }


/* -(  PhabricatorMarkupInterface Implementation  )-------------------------- */


  public function getMarkupFieldKey($field) {
    return 'AC:'.$this->getID();
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newDiffusionMarkupEngine();
  }

  public function getMarkupText($field) {
    return $this->getContent();
  }

  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }

}
