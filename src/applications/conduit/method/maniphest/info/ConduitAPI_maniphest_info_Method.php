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

class ConduitAPI_maniphest_info_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Retrieve information about a Maniphest task, given its id.";
  }

  public function defineParamTypes() {
    return array(
      'task_id' => 'required id',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_TASK' => 'No such maniphest task exists',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $task_id = $request->getValue('task_id');

    $task = id(new ManiphestTask())->load($task_id);
    if (!$task) {
      throw new ConduitException('ERR_BAD_TASK');
    }

    $result = array(
      'id'           => $task->getID(),
      'phid'         => $task->getPHID(),
      'authorPHID'   => $task->getAuthorPHID(),
      'ownerPHID'    => $task->getAuthorPHID(),
      'ccPHIDs'      => $task->getCCPHIDs(),
      'status'       => $task->getStatus(),
      'priority'     => ManiphestTaskPriority::getTaskPriorityName(
        $task->getPriority()),
      'title'        => $task->getTitle(),
      'description'  => $task->getDescription(),
      'projectPHIDs' => $task->getProjectPHIDs(),
      'uri'          => PhabricatorEnv::getProductionURI('/T'.$task->getID()),

      // Not sure what this is yet.
      // 'attached' => array($task->getAttached()),
    );
    return $result;
  }

}
