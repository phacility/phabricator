<?php

final class PhabricatorRolesSearchEngineExtension
  extends PhabricatorSearchEngineExtension {

  const EXTENSIONKEY = 'roles';

  public function isExtensionEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorRoleApplication');
  }

  public function getExtensionName() {
    return pht('Support for Roles');
  }

  public function getExtensionOrder() {
    return 3000;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorRoleInterface);
  }

  public function applyConstraintsToQuery(
    $object,
    $query,
    PhabricatorSavedQuery $saved,
    array $map) {

    if (!empty($map['rolePHIDs'])) {
      $query->withEdgeLogicConstraints(
        PhabricatorRoleObjectHasRoleEdgeType::EDGECONST,
        $map['rolePHIDs']);
    }
  }

  public function getSearchFields($object) {
    $fields = array();

    $fields[] = id(new PhabricatorRoleSearchField())
      ->setKey('rolePHIDs')
      ->setConduitKey('roles')
      ->setAliases(array('role', 'roles', 'tag', 'tags'))
      ->setLabel(pht('Tags'))
      ->setDescription(
        pht('Search for objects tagged with given roles.'));

    return $fields;
  }

  public function getSearchAttachments($object) {
    return array(
      id(new PhabricatorRolesSearchEngineAttachment())
        ->setAttachmentKey('roles'),
    );
  }


}
