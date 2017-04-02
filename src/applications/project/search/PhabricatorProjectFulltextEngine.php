<?php

final class PhabricatorProjectFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $project = $object;
    $viewer = $this->getViewer();

    // Reload the project to get slugs.
    $project = id(new PhabricatorProjectQuery())
      ->withIDs(array($project->getID()))
      ->setViewer($viewer)
      ->needSlugs(true)
      ->executeOne();

    $project->updateDatasourceTokens();

    $slugs = array();
    foreach ($project->getSlugs() as $slug) {
      $slugs[] = $slug->getSlug();
    }
    $body = implode("\n", $slugs);

    $document
      ->setDocumentTitle($project->getDisplayName())
      ->addField(PhabricatorSearchDocumentFieldType::FIELD_BODY, $body);

    $document->addRelationship(
      $project->isArchived()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $project->getPHID(),
      PhabricatorProjectProjectPHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }

}
