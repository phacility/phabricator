<?php

final class PhabricatorCustomFieldExportEngineExtension
  extends PhabricatorExportEngineExtension {

  const EXTENSIONKEY = 'custom-field';

  private $object;

  public function supportsObject($object) {
    $this->object = $object;
    return ($object instanceof PhabricatorCustomFieldInterface);
  }

  public function newExportFields() {
    $prototype = $this->object;

    $fields = $this->newCustomFields($prototype);

    $results = array();
    foreach ($fields as $field) {
      $field_key = $field->getModernFieldKey();

      $results[] = $field->newExportField()
         ->setKey($field_key);
    }

    return $results;
  }

  public function newExportData(array $objects) {
    $viewer = $this->getViewer();

    $field_map = array();
    foreach ($objects as $object) {
      $object_phid = $object->getPHID();

      $fields = PhabricatorCustomField::getObjectFields(
        $object,
        PhabricatorCustomField::ROLE_EXPORT);

      $fields
        ->setViewer($viewer)
        ->readFieldsFromObject($object);

      $field_map[$object_phid] = $fields;
    }

    $all_fields = array();
    foreach ($field_map as $field_list) {
      foreach ($field_list->getFields() as $field) {
        $all_fields[] = $field;
      }
    }

    id(new PhabricatorCustomFieldStorageQuery())
      ->addFields($all_fields)
      ->execute();

    $results = array();
    foreach ($objects as $object) {
      $object_phid = $object->getPHID();
      $object_fields = $field_map[$object_phid];

      $map = array();
      foreach ($object_fields->getFields() as $field) {
        $key = $field->getModernFieldKey();
        $map[$key] = $field->newExportData();
      }

      $results[] = $map;
    }

    return $results;
  }

  private function newCustomFields($object) {
    $fields = PhabricatorCustomField::getObjectFields(
      $object,
      PhabricatorCustomField::ROLE_EXPORT);
    $fields->setViewer($this->getViewer());

    return $fields->getFields();
  }

}
