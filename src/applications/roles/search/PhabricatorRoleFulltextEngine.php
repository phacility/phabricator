<?php

final class PhabricatorRoleFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $role = $object;
    $viewer = $this->getViewer();

    // Reload the role to get slugs.
    $role = id(new PhabricatorRoleQuery())
      ->withIDs(array($role->getID()))
      ->setViewer($viewer)
      ->needSlugs(true)
      ->executeOne();

    $role->updateDatasourceTokens();

    $slugs = array();
    foreach ($role->getSlugs() as $slug) {
      $slugs[] = $slug->getSlug();
    }
    $body = implode("\n", $slugs);

    $document
      ->setDocumentTitle($role->getDisplayName())
      ->addField(PhabricatorSearchDocumentFieldType::FIELD_BODY, $body);

    $document->addRelationship(
      $role->isArchived()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $role->getPHID(),
      PhabricatorRoleRolePHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }

}
