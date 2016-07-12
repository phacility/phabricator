<?php

final class PhameBlogFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $blog = $object;

    $document->setDocumentTitle($blog->getName());

    $document->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $blog->getDescription());

    $document->addRelationship(
      $blog->isArchived()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $blog->getPHID(),
      PhabricatorPhameBlogPHIDType::TYPECONST,
      PhabricatorTime::getNow());

  }

}
