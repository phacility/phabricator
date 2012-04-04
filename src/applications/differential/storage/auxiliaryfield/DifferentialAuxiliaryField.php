<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
