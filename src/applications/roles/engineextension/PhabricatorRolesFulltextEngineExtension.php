<?php

final class PhabricatorRolesFulltextEngineExtension
  extends PhabricatorFulltextEngineExtension {

  const EXTENSIONKEY = 'roles';

  public function getExtensionName() {
    return pht('Roles');
  }

  public function shouldEnrichFulltextObject($object) {
    return ($object instanceof PhabricatorRoleInterface);
  }

  public function enrichFulltextObject(
    $object,
    PhabricatorSearchAbstractDocument $document) {

    $role_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorRoleObjectHasRoleEdgeType::EDGECONST);

    if (!$role_phids) {
      return;
    }

    foreach ($role_phids as $role_phid) {
      $document->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_ROLE,
        $role_phid,
        PhabricatorRoleRolePHIDType::TYPECONST,
        $document->getDocumentModified()); // Bogus timestamp.
    }
  }

}
