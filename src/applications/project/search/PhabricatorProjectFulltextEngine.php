<?php

final class PhabricatorProjectFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $project = $object;
    $project->updateDatasourceTokens();

    $document->setDocumentTitle($project->getDisplayName());
    $document->addField(PhabricatorSearchDocumentFieldType::FIELD_KEYWORDS,
      $project->getPrimarySlug());
    try {
      $slugs = $project->getSlugs();
      foreach ($slugs as $slug) {}
    } catch (PhabricatorDataNotAttachedException $e) {
      // ignore
    }

    $document->addRelationship(
      $project->isArchived()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $project->getPHID(),
      PhabricatorProjectProjectPHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }

}
