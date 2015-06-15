<?php

final class PhabricatorSearchAbstractDocument extends Phobject {

  private $phid;
  private $documentType;
  private $documentTitle;
  private $documentCreated;
  private $documentModified;
  private $fields = array();
  private $relationships = array();

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
    $this->addField(PhabricatorSearchDocumentFieldType::FIELD_TITLE, $title);
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
