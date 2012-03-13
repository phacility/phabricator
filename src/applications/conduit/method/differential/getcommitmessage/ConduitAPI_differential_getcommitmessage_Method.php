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

/**
 * @group conduit
 */
final class ConduitAPI_differential_getcommitmessage_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Retrieve Differential commit messages or message templates.";
  }

  public function defineParamTypes() {
    return array(
      'revision_id' => 'optional revision_id',
      'fields' => 'optional dict<string, wild>',
      'edit' => 'optional enum<"edit", "create">',
    );
  }

  public function defineReturnType() {
    return 'nonempty string';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => 'Revision was not found.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $id = $request->getValue('revision_id');

    if ($id) {
      $revision = id(new DifferentialRevision())->load($id);
      if (!$revision) {
        throw new ConduitException('ERR_NOT_FOUND');
      }
    } else {
      $revision = new DifferentialRevision();
    }

    $revision->loadRelationships();

    $is_edit = $request->getValue('edit');
    $is_create = ($is_edit == 'create');

    $aux_fields = DifferentialFieldSelector::newSelector()
      ->getFieldSpecifications();

    foreach ($aux_fields as $key => $aux_field) {
      $aux_field->setRevision($revision);
      if (!$aux_field->shouldAppearOnCommitMessage()) {
        unset($aux_fields[$key]);
      }
    }

    $aux_fields = DifferentialAuxiliaryField::loadFromStorage(
      $revision,
      $aux_fields);
    $aux_fields = mpull($aux_fields, null, 'getCommitMessageKey');

    if ($is_edit) {
      $fields = $request->getValue('fields');
      if (!is_array($fields)) {
        $fields = array();
      }
      foreach ($fields as $field => $value) {

        $aux_field = idx($aux_fields, $field);
        if (!$aux_field) {
          throw new Exception(
            "Commit message includes field '{$field}' which does not ".
            "correspond to any configured field.");
        }

        if ($is_create ||
            $aux_field->shouldOverwriteWhenCommitMessageIsEdited()) {
          $aux_field->setValueFromParsedCommitMessage($value);
        }
      }
    }


    $aux_phids = array();
    foreach ($aux_fields as $field_key => $field) {
      $aux_phids[$field_key] = $field->getRequiredHandlePHIDsForCommitMessage();
    }
    $phids = array_unique(array_mergev($aux_phids));
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    foreach ($aux_fields as $field_key => $field) {
      $field->setHandles(array_select_keys($handles, $aux_phids[$field_key]));
    }


    $commit_message = array();
    foreach ($aux_fields as $field_key => $field) {
      $value = $field->renderValueForCommitMessage($is_edit);
      $label = $field->renderLabelForCommitMessage();
      if (!strlen($value)) {
        if ($field_key === 'title') {
          $commit_message[] = '<<Enter Revision Title>>';
        } else {
          if ($field->shouldAppearOnCommitMessageTemplate() && $is_edit) {
            $commit_message[] = $label.': ';
          }
        }
      } else {
        if ($field_key === 'title') {
          $commit_message[] = $value;
        } else {
          $value = str_replace(
            array("\r\n", "\r"),
            array("\n",   "\n"),
            $value);
          if (strpos($value, "\n") !== false) {
            $commit_message[] = "{$label}:\n{$value}";
          } else {
            $commit_message[] = "{$label}: {$value}";
          }
        }
      }
    }
    $commit_message = implode("\n\n", $commit_message);

    return $commit_message;
  }

}
