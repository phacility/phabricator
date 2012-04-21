<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
