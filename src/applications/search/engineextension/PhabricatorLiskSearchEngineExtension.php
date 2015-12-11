<?php

final class PhabricatorLiskSearchEngineExtension
  extends PhabricatorSearchEngineExtension {

  const EXTENSIONKEY = 'lisk';

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('Lisk Builtin Properties');
  }

  public function supportsObject($object) {
    if (!($object instanceof LiskDAO)) {
      return false;
    }

    if (!$object->getConfigOption(LiskDAO::CONFIG_TIMESTAMPS)) {
      return false;
    }

    return true;
  }

  public function getFieldSpecificationsForConduit($object) {
    return array(
      'dateCreated' => array(
        'type' => 'int',
        'description' => pht(
          'Epoch timestamp when the object was created.'),
      ),
      'dateModified' => array(
        'type' => 'int',
        'description' => pht(
          'Epoch timestamp when the object was last updated.'),
      ),
    );
  }

  public function getFieldValuesForConduit($object) {
    return array(
      'dateCreated' => (int)$object->getDateCreated(),
      'dateModified' => (int)$object->getDateModified(),
    );
  }

}
