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
final class ConduitAPI_maniphest_createtask_Method
  extends ConduitAPI_maniphest_Method {

  public function getMethodDescription() {
    return "Create a new Maniphest task.";
  }

  public function defineParamTypes() {
    return $this->getTaskFields($is_new = true);
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' => 'Missing or malformed parameter.'
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $task = new ManiphestTask();
    $task->setPriority(ManiphestTaskPriority::getDefaultPriority());
    $task->setAuthorPHID($request->getUser()->getPHID());

    $this->applyRequest($task, $request, $is_new = true);

    return $this->buildTaskInfoDictionary($task);
  }

}
