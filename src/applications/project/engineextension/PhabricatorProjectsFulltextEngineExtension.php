<?php

final class PhabricatorProjectsFulltextEngineExtension
  extends PhabricatorFulltextEngineExtension {

  const EXTENSIONKEY = 'projects';

  public function getExtensionName() {
    return pht('Projects');
  }

  public function shouldIndexFulltextObject($object) {
    return ($object instanceof PhabricatorProjectInterface);
  }

  public function indexFulltextObject(
    $object,
    PhabricatorSearchAbstractDocument $document) {

    $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);

    if (!$project_phids) {
      return;
    }

    foreach ($project_phids as $project_phid) {
      $document->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_PROJECT,
        $project_phid,
        PhabricatorProjectProjectPHIDType::TYPECONST,
        $document->getDocumentModified()); // Bogus timestamp.
    }
  }

}
