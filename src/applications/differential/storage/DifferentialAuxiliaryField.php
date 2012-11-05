<?php

final class DifferentialAuxiliaryField extends DifferentialDAO {

  protected $revisionPHID;
  protected $name;
  protected $value;

  public function setName($name) {
    if (strlen($name) > 32) {
      throw new Exception(
        "Tried to set name '{$name}' for a Differential auxiliary field; ".
        "auxiliary field names must be no longer than 32 characters.");
    }
    $this->name = $name;
    return $this;
  }

  public static function loadFromStorage(
    DifferentialRevision $revision,
    array $aux_fields) {
    assert_instances_of($aux_fields, 'DifferentialFieldSpecification');

    $storage_keys = array_filter(mpull($aux_fields, 'getStorageKey'));
    $field_data = array();
    if ($storage_keys) {
      $field_data = id(new DifferentialAuxiliaryField())->loadAllWhere(
        'revisionPHID = %s AND name IN (%Ls)',
        $revision->getPHID(),
        $storage_keys);
      $field_data = mpull($field_data, 'getValue', 'getName');
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
