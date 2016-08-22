<?php

final class PhabricatorSpacesSearchEngineExtension
  extends PhabricatorSearchEngineExtension {

  const EXTENSIONKEY = 'spaces';

  public function isExtensionEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorSpacesApplication');
  }

  public function getExtensionName() {
    return pht('Support for Spaces');
  }

  public function getExtensionOrder() {
    return 4000;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorSpacesInterface);
  }

  public function getSearchFields($object) {
    $fields = array();

    if (PhabricatorSpacesNamespaceQuery::getSpacesExist()) {
      $fields[] = id(new PhabricatorSpacesSearchField())
        ->setKey('spacePHIDs')
        ->setConduitKey('spaces')
        ->setAliases(array('space', 'spaces'))
        ->setLabel(pht('Spaces'))
        ->setDescription(
          pht('Search for objects in certain spaces.'));
    }

    return $fields;
  }

  public function applyConstraintsToQuery(
    $object,
    $query,
    PhabricatorSavedQuery $saved,
    array $map) {

    if (!empty($map['spacePHIDs'])) {
      $query->withSpacePHIDs($map['spacePHIDs']);
    } else {
      // If the user doesn't search for objects in specific spaces, we
      // default to "all active spaces you have permission to view".
      $query->withSpaceIsArchived(false);
    }
  }

  public function getFieldSpecificationsForConduit($object) {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('spacePHID')
        ->setType('phid?')
        ->setDescription(
          pht('PHID of the policy space this object is part of.')),
    );
  }

  public function getFieldValuesForConduit($object, $data) {
    return array(
      'spacePHID' => $object->getSpacePHID(),
    );
  }

}
