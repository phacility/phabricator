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

/**
 * @group conduit
 */
abstract class ConduitAPI_maniphest_Method extends ConduitAPIMethod {

  protected function buildTaskInfoDictionary(ManiphestTask $task) {
    $auxiliary = $task->loadAuxiliaryAttributes();
    $auxiliary = mpull($auxiliary, 'getValue', 'getName');

    $result = array(
      'id'           => $task->getID(),
      'phid'         => $task->getPHID(),
      'authorPHID'   => $task->getAuthorPHID(),
      'ownerPHID'    => $task->getOwnerPHID(),
      'ccPHIDs'      => $task->getCCPHIDs(),
      'status'       => $task->getStatus(),
      'priority'     => ManiphestTaskPriority::getTaskPriorityName(
        $task->getPriority()),
      'title'        => $task->getTitle(),
      'description'  => $task->getDescription(),
      'projectPHIDs' => $task->getProjectPHIDs(),
      'uri'          => PhabricatorEnv::getProductionURI('/T'.$task->getID()),
      'auxiliary'    => $auxiliary,

      'objectName'   => 'T'.$task->getID(),
      'dateCreated'  => $task->getDateCreated(),
      'dateModified' => $task->getDateModified(),
    );

    return $result;
  }

}
