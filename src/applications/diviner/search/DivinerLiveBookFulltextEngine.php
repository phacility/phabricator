<?php

final class DivinerLiveBookFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $book = $object;

    $document->setDocumentTitle($book->getTitle());

    $document->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $book->getPreface());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_REPOSITORY,
      $book->getRepositoryPHID(),
      PhabricatorRepositoryRepositoryPHIDType::TYPECONST,
      $book->getDateCreated());
  }


}
