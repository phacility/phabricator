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
    $query->setQuery($request->getStr('query'));
    $query->setParameter('type', 'TASK');

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
    $results = ipull($results, 'phid');

    $handles = id(new PhabricatorObjectHandleData($results))
      ->loadHandles();

    $data = array();
    foreach ($handles as $handle) {
      $view = new PhabricatorHandleObjectSelectorDataView($handle);
      $data[] = $view->renderData();
    }

    return id(new AphrontAjaxResponse())->setContent($data);
  }
}
