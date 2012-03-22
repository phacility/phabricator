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
final class ConduitAPI_differential_updatetaskrevisionassoc_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Given a task together with its original and new associated ".
      "revisions, update the revisions for their attached_tasks.";
  }

  public function defineParamTypes() {
    return array(
      'task_phid' => 'required nonempty string',
      'orig_rev_phids' => 'required list<string>',
      'new_rev_phids' => 'required list<string>',
    );
  }

  public function defineReturnType() {
    return 'void';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_NO_TASKATTACHER_DEFINED' => 'No task attacher defined.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $task_phid = $request->getValue('task_phid');
    $orig_rev_phids = $request->getValue('orig_rev_phids');
    if (empty($orig_rev_phids)) {
      $orig_rev_phids = array();
    }

    $new_rev_phids = $request->getValue('new_rev_phids');
    if (empty($new_rev_phids)) {
      $new_rev_phids = array();
    }

    try {
      $task_attacher = PhabricatorEnv::newObjectFromConfig(
        'differential.attach-task-class');
      $task_attacher->updateTaskRevisionAssoc(
        $task_phid,
        $orig_rev_phids,
        $new_rev_phids);
    } catch (ReflectionException $ex) {
      throw new ConduitException('ERR_NO_TASKATTACHER_DEFINED');
    }
  }

}

