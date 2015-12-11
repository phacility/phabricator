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

  public function supportsObject($object) {
    return ($object instanceof PhabricatorSpacesInterface);
  }

  public function getFieldSpecificationsForConduit($object) {
    return array(
      'spacePHID' => array(
        'type' => 'phid?',
        'description' => pht(
          'PHID of the policy space this object is part of.'),
      ),
    );
  }

  public function getFieldValuesForConduit($object) {
    return array(
      'spacePHID' => $object->getSpacePHID(),
    );
  }

}
