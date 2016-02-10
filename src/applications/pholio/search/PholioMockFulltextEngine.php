<?php

final class PholioMockFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $mock = $object;

    $document->setDocumentTitle($mock->getName());

    $document->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $mock->getDescription());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $mock->getAuthorPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $mock->getDateCreated());
  }

}
