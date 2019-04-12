<?php

final class PhabricatorDashboardFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $dashboard = $object;

    $document->setDocumentTitle($dashboard->getName());

    $document->addRelationship(
      $dashboard->isArchived()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $dashboard->getPHID(),
      PhabricatorDashboardDashboardPHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }

}
