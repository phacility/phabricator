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
final class ConduitAPI_maniphest_update_Method
  extends ConduitAPI_maniphest_Method {

  public function getMethodDescription() {
    return "Update an existing Maniphest task.";
  }

  public function defineParamTypes() {
    return $this->getTaskFields($is_new = false);
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-BAD-TASK'  => 'No such task exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $id = $request->getValue('id');
    $phid = $request->getValue('phid');

    if (($id && $phid) || (!$id && !$phid)) {
      throw new Exception("Specify exactly one of 'id' and 'phid'.");
    }

    if ($id) {
      $task = id(new ManiphestTask())->load($id);
    } else {
      $task = id(new ManiphestTask())->loadOneWhere(
        'phid = %s',
        $phid);
    }

    if (!$task) {
      throw new ConduitException('ERR-BAD-TASK');
    }

    $this->applyRequest($task, $request, $is_new = false);

    return $this->buildTaskInfoDictionary($task);
  }

}
