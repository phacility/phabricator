<?php

final class PhrictionContent
  extends PhrictionDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $documentID;
  protected $version;
  protected $authorPHID;

  protected $title;
  protected $slug;
  protected $content;
  protected $description;

  protected $changeType;
  protected $changeRef;

  private $document = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'version' => 'uint32',
        'title' => 'sort',
        'slug' => 'text128',
        'content' => 'text',
        'changeType' => 'uint32',
        'changeRef' => 'uint32?',

        // T6203/NULLABILITY
        // This should just be empty if not provided?
        'description' => 'text?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'documentID' => array(
          'columns' => array('documentID', 'version'),
          'unique' => true,
        ),
        'authorPHID' => array(
          'columns' => array('authorPHID'),
        ),
        'slug' => array(
          'columns' => array('slug'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhrictionContentPHIDType::TYPECONST;
  }

  public function newRemarkupView(PhabricatorUser $viewer) {
    return id(new PHUIRemarkupView($viewer, $this->getContent()))
      ->setRemarkupOption(PHUIRemarkupView::OPTION_GENERATE_TOC, true)
      ->setGenerateTableOfContents(true);
  }

  public function attachDocument(PhrictionDocument $document) {
    $this->document = $document;
    return $this;
  }

  public function getDocument() {
    return $this->assertAttached($this->document);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    return array(
      array($this->getDocument(), PhabricatorPolicyCapability::CAN_VIEW),
    );
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->delete();
  }

}
