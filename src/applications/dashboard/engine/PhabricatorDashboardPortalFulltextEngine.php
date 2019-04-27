<?php

final class PhabricatorDashboardPortalFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $portal = $object;

    $document->setDocumentTitle($portal->getName());

    $document->addRelationship(
      $portal->isArchived()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $portal->getPHID(),
      PhabricatorDashboardPortalPHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }

}
