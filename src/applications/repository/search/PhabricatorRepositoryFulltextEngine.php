<?php

final class PhabricatorRepositoryFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {
    $repo = $object;
    $document->setDocumentTitle($repo->getName());
    $document->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $repo->getRepositorySlug()."\n".$repo->getDetail('description'));

    $document->setDocumentCreated($repo->getDateCreated());
    $document->setDocumentModified($repo->getDateModified());

    $document->addRelationship(
      $repo->isTracked()
        ? PhabricatorSearchRelationship::RELATIONSHIP_OPEN
        : PhabricatorSearchRelationship::RELATIONSHIP_CLOSED,
      $repo->getPHID(),
      PhabricatorRepositoryRepositoryPHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }

}
