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

class ManiphestTaskSelectorSearchController extends ManiphestController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = new PhabricatorSearchQuery();

    $query_str = $request->getStr('query');
    $matches = array();
    $task_ids = array();

    // Collect all task IDs, e.g., T12 T651 T631, from the query string
    preg_match_all('/\bT(\d+)\b/', $query_str, $matches);
    if ($matches) {
      $task_ids = $matches[1];
    }

    $query->setQuery($query_str);
    $query->setParameter('type', PhabricatorPHIDConstants::PHID_TYPE_TASK);

    switch ($request->getStr('filter')) {
      case 'assigned':
        $query->setParameter('owner', array($user->getPHID()));
        $query->setParameter('open', 1);
        break;
      case 'created';
        $query->setParameter('author', array($user->getPHID()));
        $query->setParameter('open', 1);
        break;
      case 'open':
        $query->setParameter('open', 1);
        break;
    }

    $exec = new PhabricatorSearchMySQLExecutor();
    $results = $exec->executeSearch($query);

    $phids = array();

    foreach ($results as $result) {
      $phids[$result['phid']] = true;
    }

    // Do a separate query for task IDs if the query had them
    if ($task_ids) {
      $task_object = new ManiphestTask();

      // It's OK to ignore filters, if user wants specific task IDs
      $tasks = $task_object->loadAllWhere('id IN (%Ls)', $task_ids);

      foreach ($tasks as $task) {
        $phids[$task->getPHID()] = true;
      }
    }

    $phids = array_keys($phids);
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $data = array();
    foreach ($handles as $handle) {
      $view = new PhabricatorHandleObjectSelectorDataView($handle);
      $data[] = $view->renderData();
    }

    return id(new AphrontAjaxResponse())->setContent($data);
  }
}
