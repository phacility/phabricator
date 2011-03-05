<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class ConduitAPI_differential_getcommitmessage_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Retrieve Differential commit messages.";
  }

  public function defineParamTypes() {
    return array(
      'revision_id' => 'required revision_id',
      'fields' => 'optional dict<string, wild>',
      'edit' => 'optional bool',
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

    $revision = id(new DifferentialRevision())->load($id);
    if (!$revision) {
      throw new ConduitException('ERR_NOT_FOUND');
    }

    $edit = $request->getValue('edit');
    $mode = $edit
      ? DifferentialCommitMessageData::MODE_EDIT
      : DifferentialCommitMessageData::MODE_AMEND;

    $message_data = new DifferentialCommitMessageData($revision, $mode);
    $message_data->prepare();

    if ($mode == DifferentialCommitMessageData::MODE_EDIT) {
      $fields = $request->getValue('fields');
      if ($fields) {

        static $simple_fields = array(
          'title'           => 'Title',
          'summary'         => 'Summary',
          'testPlan'        => 'Test Plan',
          'blameRevision'   => 'Blame Revision',
          'revertPlan'      => 'Revert Plan',
        );

        foreach ($fields as $field => $value) {
          if (isset($simple_fields[$field])) {
            $message_data->overwriteFieldValue(
              $simple_fields[$field],
              $value);
          } else {
            $overwrite = true;
            static $overwrite_map = array(
              'reviewerPHIDs' => 'Reviewers',
              'ccPHIDs' => 'CC',
              'taskPHIDs' => 'Tasks',
            );
            switch ($field) {
              case 'reviewerPHIDs':
              case 'ccPHIDs':
                $handles = id(new PhabricatorObjectHandleData($value))
                  ->loadHandles($handles);
                $value = implode(', ', mpull($handles, 'getName'));
                break;
              default:
                $overwrite = false;
                break;
            }
            if ($overwrite) {
              $message_data->overwriteFieldValue(
                $overwrite_map[$field],
                $value);
            }
          }
        }
      }
    }

    $commit_message = $message_data->getCommitMessage();

    return wordwrap($commit_message, 80);
  }

}
