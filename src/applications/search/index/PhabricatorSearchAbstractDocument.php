<?php

/**
 * @group search
 */
final class PhabricatorSearchAbstractDocument {

  private $phid;
  private $documentType;
  private $documentTitle;
  private $documentCreated;
  private $documentModified;
  private $fields = array();
  private $relationships = array();

  public static function getSupportedTypes() {
    $more = PhabricatorEnv::getEnvConfig('search.more-document-types', array());
    return array(
      PhabricatorPHIDConstants::PHID_TYPE_DREV => 'Differential Revisions',
      PhabricatorPHIDConstants::PHID_TYPE_CMIT => 'Repository Commits',
      PhabricatorPHIDConstants::PHID_TYPE_TASK => 'Maniphest Tasks',
      PhabricatorPHIDConstants::PHID_TYPE_WIKI => 'Phriction Documents',
      PhabricatorPHIDConstants::PHID_TYPE_USER => 'Phabricator Users',
      PhabricatorPHIDConstants::PHID_TYPE_QUES => 'Ponder Questions',
    ) + $more;
  }

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function setDocumentType($document_type) {
    $this->documentType = $document_type;
    return $this;
  }

  public function setDocumentTitle($title) {
    $this->documentTitle = $title;
    $this->addField(PhabricatorSearchField::FIELD_TITLE, $title);
    return $this;
  }

  public function addField($field, $corpus, $aux_phid = null) {
    $this->fields[] = array($field, $corpus, $aux_phid);
    return $this;
  }

  public function addRelationship($type, $related_phid, $rtype, $time) {
    $this->relationships[] = array($type, $related_phid, $rtype, $time);
    return $this;
  }

  public function setDocumentCreated($date) {
    $this->documentCreated = $date;
    return $this;
  }

  public function setDocumentModified($date) {
    $this->documentModified = $date;
    return $this;
  }

  public function getPHID() {
    return $this->phid;
  }

  public function getDocumentType() {
    return $this->documentType;
  }

  public function getDocumentTitle() {
    return $this->documentTitle;
  }

  public function getDocumentCreated() {
    return $this->documentCreated;
  }

  public function getDocumentModified() {
    return $this->documentModified;
  }

  public function getFieldData() {
    return $this->fields;
  }

  public function getRelationshipData() {
    return $this->relationships;
  }
}
