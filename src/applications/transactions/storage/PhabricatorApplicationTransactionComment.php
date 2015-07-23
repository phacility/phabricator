<?php

abstract class PhabricatorApplicationTransactionComment
  extends PhabricatorLiskDAO
  implements
    PhabricatorMarkupInterface,
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

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

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'transactionPHID' => 'phid?',
        'commentVersion' => 'uint32',
        'content' => 'text',
        'contentSource' => 'text',
        'isDeleted' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_version' => array(
          'columns' => array('transactionPHID', 'commentVersion'),
          'unique' => true,
        ),
      ),
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

  public function setContentSourceFromRequest(AphrontRequest $request) {
    return $this->setContentSource(
      PhabricatorContentSource::newFromRequest($request));
  }

  public function getContentSource() {
    return PhabricatorContentSource::newFromSerialized($this->contentSource);
  }

  public function getIsRemoved() {
    return ($this->getIsDeleted() == 2);
  }

  public function setIsRemoved($removed) {
    if ($removed) {
      $this->setIsDeleted(2);
    } else {
      $this->setIsDeleted(0);
    }
    return $this;
  }


/* -(  PhabricatorMarkupInterface  )----------------------------------------- */


  public function getMarkupFieldKey($field) {
    return PhabricatorPHIDConstants::PHID_TYPE_XCMT.':'.$this->getPHID();
  }


  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::getEngine();
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

  public function describeAutomaticCapability($capability) {
    return pht(
      'Comments are visible to users who can see the object which was '.
      'commented on. Comments can be edited by their authors.');
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->openTransaction();
      $this->delete();
    $this->saveTransaction();
  }

}
