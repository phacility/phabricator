<?php

final class DivinerLiveSymbolFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $atom = $object;
    $book = $atom->getBook();

    $document
      ->setDocumentTitle($atom->getTitle())
      ->setDocumentCreated($book->getDateCreated())
      ->setDocumentModified($book->getDateModified());

    $document->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $atom->getSummary());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_BOOK,
      $atom->getBookPHID(),
      DivinerBookPHIDType::TYPECONST,
      PhabricatorTime::getNow());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_REPOSITORY,
      $atom->getRepositoryPHID(),
      PhabricatorRepositoryRepositoryPHIDType::TYPECONST,
      PhabricatorTime::getNow());

    $document->addRelationship(
      $atom->getGraphHash()
        ? PhabricatorSearchRelationship::RELATIONSHIP_OPEN
        : PhabricatorSearchRelationship::RELATIONSHIP_CLOSED,
      $atom->getBookPHID(),
      DivinerBookPHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }

}
