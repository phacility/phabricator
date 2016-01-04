<?php

final class PhabricatorProjectFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $project = $object;
    $project->updateDatasourceTokens();

    $document->setDocumentTitle($project->getName());

    $document->addRelationship(
      $project->isArchived()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $project->getPHID(),
      PhabricatorProjectProjectPHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }

}
