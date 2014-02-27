<?php

final class DifferentialAuxiliaryField {

  public static function loadFromStorage(
    DifferentialRevision $revision,
    array $aux_fields) {
    assert_instances_of($aux_fields, 'DifferentialFieldSpecification');

    $storage_keys = array_filter(mpull($aux_fields, 'getStorageKey'));
    $field_data = array();
    if ($storage_keys) {
      $index_map = array();
      foreach ($storage_keys as $key) {
        $index_map[PhabricatorHash::digestForIndex($key)] = $key;
      }

      $index_data = id(new DifferentialCustomFieldStorage())->loadAllWhere(
        'objectPHID = %s AND fieldIndex IN (%Ls)',
        $revision->getPHID(),
        array_keys($index_map));
      $index_data = mpull($index_data, 'getFieldValue', 'getFieldIndex');

      foreach ($index_data as $index => $data) {
        $field_data[$index_map[$index]] = $data;
      }
    }

    foreach ($aux_fields as $aux_field) {
      $aux_field->setRevision($revision);
      $key = $aux_field->getStorageKey();
      if ($key) {
        $aux_field->setValueFromStorage(idx($field_data, $key));
      }
    }

    return $aux_fields;
  }

}
