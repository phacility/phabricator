<?php

final class PhabricatorDashboardPanelFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $panel = $object;

    $document->setDocumentTitle($panel->getName());

    $document->addRelationship(
      $panel->getIsArchived()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $panel->getPHID(),
      PhabricatorDashboardPanelPHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }

}
