<?php

final class PhabricatorCustomFieldSearchEngineExtension
  extends PhabricatorSearchEngineExtension {

  const EXTENSIONKEY = 'customfield';

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('Support for Custom Fields');
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorCustomFieldInterface);
  }

  public function getFieldSpecificationsForConduit($object) {
    $fields = PhabricatorCustomField::getObjectFields(
      $object,
      PhabricatorCustomField::ROLE_CONDUIT);

    $map = array();
    foreach ($fields->getFields() as $field) {
      $key = $field->getModernFieldKey();
      $map[$key] = array(
        'type' => 'wild',
        'description' => $field->getFieldDescription(),
      );
    }

    return $map;
  }

  public function getFieldValuesForConduit($object) {
    // TODO: This is currently very inefficient. We should bulk-load these
    // field values instead.

    $fields = PhabricatorCustomField::getObjectFields(
      $object,
      PhabricatorCustomField::ROLE_CONDUIT);

    $fields
      ->setViewer($this->getViewer())
      ->readFieldsFromStorage($object);

    $map = array();
    foreach ($fields->getFields() as $field) {
      $key = $field->getModernFieldKey();
      $map[$key] = $field->getConduitDictionaryValue();
    }

    return $map;
  }

}
