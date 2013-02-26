<?php

abstract class PhabricatorApplicationTransactionComment
  extends PhabricatorLiskDAO
  implements PhabricatorMarkupInterface, PhabricatorPolicyInterface {

  const MARKUP_FIELD_COMMENT  = 'markup:comment';

  protected $transactionPHID;
  protected $commentVersion;
  protected $authorPHID;
  protected $viewPolicy;
  protected $editPolicy;
  protected $content;
  protected $contentSource;
  protected $isDeleted = 0;

  abstract public function getApplicationTransactionObject();

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_XCMT);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function getApplicationName() {
    return $this->getApplicationTransactionObject()->getApplicationName();
  }

  public function getTableName() {
    $xaction = $this->getApplicationTransactionObject();
    return self::getTableNameFromTransaction($xaction);
  }

  public static function getTableNameFromTransaction(
    PhabricatorApplicationTransaction $xaction) {
    return $xaction->getTableName().'_comment';
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source->serialize();
    return $this;
  }

  public function getContentSource() {
    return PhabricatorContentSource::newFromSerialized($this->contentSource);
  }


/* -(  PhabricatorMarkupInterface  )----------------------------------------- */


  public function getMarkupFieldKey($field) {
    return PhabricatorPHIDConstants::PHID_TYPE_XCMT.':'.$this->getPHID();
  }


  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newMarkupEngine(array());
  }


  public function getMarkupText($field) {
    return $this->getContent();
  }


  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    require_celerity_resource('phabricator-remarkup-css');
    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $output);
  }


  public function shouldUseMarkupCache($field) {
    return (bool)$this->getPHID();
  }

/* -(  PhabricatorPolicyInterface Implementation  )-------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getAuthorPHID());
  }

}
