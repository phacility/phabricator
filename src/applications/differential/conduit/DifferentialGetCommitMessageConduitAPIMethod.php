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
      $revision->attachReviewerStatus(array());
      $revision->attachActiveDiff(null);
    }

    $is_edit = $request->getValue('edit');
    $is_create = ($is_edit == 'create');

    $field_list = PhabricatorCustomField::getObjectFields(
      $revision,
      ($is_edit
        ? DifferentialCustomField::ROLE_COMMITMESSAGEEDIT
        : DifferentialCustomField::ROLE_COMMITMESSAGE));

    $field_list
      ->setViewer($viewer)
      ->readFieldsFromStorage($revision);

    $field_map = mpull($field_list->getFields(), null, 'getFieldKeyForConduit');

    if ($is_edit) {
      $fields = $request->getValue('fields', array());
      foreach ($fields as $field => $value) {
        $custom_field = idx($field_map, $field);
        if (!$custom_field) {
          // Just ignore this, these workflows don't make strong distictions
          // about field editability on the client side.
          continue;
        }
        if ($is_create ||
            $custom_field->shouldOverwriteWhenCommitMessageIsEdited()) {
          $custom_field->readValueFromCommitMessage($value);
        }
      }
    }

    $phids = array();
    foreach ($field_list->getFields() as $key => $field) {
      $field_phids = $field->getRequiredHandlePHIDsForCommitMessage();
      if (!is_array($field_phids)) {
        throw new Exception(
          pht(
            'Custom field "%s" was expected to return an array of handle '.
            'PHIDs required for commit message rendering, but returned "%s" '.
            'instead.',
            $field->getFieldKey(),
            gettype($field_phids)));
      }
      $phids[$key] = $field_phids;
    }

    $all_phids = array_mergev($phids);
    if ($all_phids) {
      $all_handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs($all_phids)
        ->execute();
    } else {
      $all_handles = array();
    }

    $key_title = id(new DifferentialTitleField())->getFieldKey();
    $default_title = DifferentialTitleField::getDefaultTitle();

    $commit_message = array();
    foreach ($field_list->getFields() as $key => $field) {
      $handles = array_select_keys($all_handles, $phids[$key]);

      $label = $field->renderCommitMessageLabel();
      $value = $field->renderCommitMessageValue($handles);

      if (!is_string($value) && !is_null($value)) {
        throw new Exception(
          pht(
            'Custom field "%s" was expected to render a string or null value, '.
            'but rendered a "%s" instead.',
            $field->getFieldKey(),
            gettype($value)));
      }

      $is_title = ($key == $key_title);

      if (!strlen($value)) {
        if ($is_title) {
          $commit_message[] = $default_title;
        } else {
          if ($is_edit && $field->shouldAppearInCommitMessageTemplate()) {
            $commit_message[] = $label.': ';
          }
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

    if ($is_edit) {
      $tip = $this->getProTip($field_list);
      if ($tip !== null) {
        $commit_message[] = "\n".$tip;
      }
    }

    $commit_message = implode("\n\n", $commit_message);

    return $commit_message;
  }

  private function getProTip() {
    // Any field can provide tips, whether it normally appears on commit
    // messages or not.
    $field_list = PhabricatorCustomField::getObjectFields(
      new DifferentialRevision(),
      PhabricatorCustomField::ROLE_DEFAULT);

    $tips = array();
    foreach ($field_list->getFields() as $key => $field) {
      $tips[] = $field->getProTips();
    }
    $tips = array_mergev($tips);

    if (!$tips) {
      return null;
    }

    shuffle($tips);

    $tip = pht('Tip: %s', head($tips));
    $tip = wordwrap($tip, 78, "\n", true);

    $lines = explode("\n", $tip);
    foreach ($lines as $key => $line) {
      $lines[$key] = '# '.$line;
    }

    return implode("\n", $lines);
  }

}
