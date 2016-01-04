<?php

final class PhrictionDocumentFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $wiki = id(new PhrictionDocumentQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($document->getPHID()))
      ->needContent(true)
      ->executeOne();

    $content = $wiki->getContent();

    $document->setDocumentTitle($content->getTitle());

    // TODO: These are not quite correct, but we don't currently store the
    // proper dates in a way that's easy to get to.
    $document
      ->setDocumentCreated($content->getDateCreated())
      ->setDocumentModified($content->getDateModified());

    $document->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $content->getContent());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $content->getAuthorPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $content->getDateCreated());

    $document->addRelationship(
      ($wiki->getStatus() == PhrictionDocumentStatus::STATUS_EXISTS)
        ? PhabricatorSearchRelationship::RELATIONSHIP_OPEN
        : PhabricatorSearchRelationship::RELATIONSHIP_CLOSED,
      $wiki->getPHID(),
      PhrictionDocumentPHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }
}
