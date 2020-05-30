<?php

final class PhabricatorPasteFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $paste = id(new PhabricatorPasteQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($object->getPHID()))
      ->needContent(true)
      ->executeOne();

    $document->setDocumentTitle($paste->getTitle());

    $document->addRelationship(
      $paste->isArchived()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $paste->getPHID(),
      PhabricatorPastePastePHIDType::TYPECONST,
      PhabricatorTime::getNow());

    $document->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $paste->getContent());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $paste->getAuthorPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $paste->getDateCreated());
  }

}
