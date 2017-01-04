<?php

final class DifferentialGetCommitMessageConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.getcommitmessage';
  }

  public function getMethodDescription() {
    return pht('Retrieve Differential commit messages or message templates.');
  }

  protected function defineParamTypes() {
    $edit_types = array('edit', 'create');

    return array(
      'revision_id' => 'optional revision_id',
      'fields' => 'optional dict<string, wild>',
      'edit' => 'optional '.$this->formatStringConstants($edit_types),
    );
  }

  protected function defineReturnType() {
    return 'nonempty string';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => pht('Revision was not found.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $id = $request->getValue('revision_id');
    $viewer = $request->getUser();

    if ($id) {
      $revision = id(new DifferentialRevisionQuery())
        ->withIDs(array($id))
        ->setViewer($viewer)
        ->needReviewerStatus(true)
        ->needActiveDiffs(true)
        ->executeOne();
      if (!$revision) {
        throw new ConduitException('ERR_NOT_FOUND');
      }
    } else {
      $revision = DifferentialRevision::initializeNewRevision($viewer);
    }

    // There are three modes here: "edit", "create", and "read" (which has
    // no value for the "edit" parameter).

    // In edit or create mode, we hide read-only fields. In create mode, we
    // show "Field:" templates for some fields even if they are empty.
    $edit_mode = $request->getValue('edit');

    $is_any_edit = (bool)strlen($edit_mode);
    $is_create = ($edit_mode == 'create');

    $field_list = DifferentialCommitMessageField::newEnabledFields($viewer);

    $custom_storage = $this->loadCustomFieldStorage($viewer, $revision);
    foreach ($field_list as $field) {
      $field->setCustomFieldStorage($custom_storage);
    }

    // If we're editing the message, remove fields like "Conflicts" and
    // "git-svn-id" which should not be presented to the user for editing.
    if ($is_any_edit) {
      foreach ($field_list as $field_key => $field) {
        if (!$field->isFieldEditable()) {
          unset($field_list[$field_key]);
        }
      }
    }

    $overrides = $request->getValue('fields', array());

    $value_map = array();
    foreach ($field_list as $field_key => $field) {
      if (array_key_exists($field_key, $overrides)) {
        $field_value = $overrides[$field_key];
      } else {
        $field_value = $field->readFieldValueFromObject($revision);
      }

      // We're calling this method on the value no matter where we got it
      // from, so we hit the same validation logic for values which came over
      // the wire and which we generated.
      $field_value = $field->readFieldValueFromConduit($field_value);

      $value_map[$field_key] = $field_value;
    }

    $key_title = DifferentialTitleCommitMessageField::FIELDKEY;

    $commit_message = array();
    foreach ($field_list as $field_key => $field) {
      $label = $field->getFieldName();
      $wire_value = $value_map[$field_key];
      $value = $field->renderFieldValue($wire_value);

      $is_template = ($is_create && $field->isTemplateField());

      if (!is_string($value) && !is_null($value)) {
        throw new Exception(
          pht(
            'Commit message field "%s" was expected to render a string or '.
            'null value, but rendered a "%s" instead.',
            $field->getFieldKey(),
            gettype($value)));
      }

      $is_title = ($field_key == $key_title);

      if (!strlen($value)) {
        if ($is_template) {
          $commit_message[] = $label.': ';
        }
      } else {
        if ($is_title) {
          $commit_message[] = $value;
        } else {
          $value = str_replace(
            array("\r\n", "\r"),
            array("\n",   "\n"),
            $value);
          if (strpos($value, "\n") !== false || substr($value, 0, 2) === '  ') {
            $commit_message[] = "{$label}:\n{$value}";
          } else {
            $commit_message[] = "{$label}: {$value}";
          }
        }
      }
    }

    return implode("\n\n", $commit_message);
  }

  private function loadCustomFieldStorage(
    PhabricatorUser $viewer,
    DifferentialRevision $revision) {
    $custom_field_list = PhabricatorCustomField::getObjectFields(
      $revision,
      DifferentialCustomField::ROLE_COMMITMESSAGE);
    $custom_field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($revision);

    $custom_field_map = array();
    foreach ($custom_field_list->getFields() as $custom_field) {
      if (!$custom_field->shouldUseStorage()) {
        continue;
      }
      $custom_field_key = $custom_field->getFieldKey();
      $custom_field_value = $custom_field->getValueForStorage();
      $custom_field_map[$custom_field_key] = $custom_field_value;
    }

    return $custom_field_map;
  }


}
