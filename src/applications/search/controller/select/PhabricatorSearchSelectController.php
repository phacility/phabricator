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
 * @group search
 */
final class PhabricatorSearchSelectController
  extends PhabricatorSearchBaseController {

  private $type;

  public function willProcessRequest(array $data) {
    $this->type = $data['type'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = new PhabricatorSearchQuery();

    $query_str = $request->getStr('query');
    $matches = array();

    $query->setQuery($query_str);
    $query->setParameter('type', $this->type);

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

    $query->setParameter('exclude', $request->getStr('exclude'));

    $engine = PhabricatorSearchEngineSelector::newSelector()->newEngine();
    $results = $engine->executeSearch($query);

    $phids = array_fill_keys($results, true);
    $phids += $this->queryObjectNames($query_str);

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

  private function queryObjectNames($query) {

    $pattern = null;
    switch ($this->type) {
      case PhabricatorPHIDConstants::PHID_TYPE_TASK:
        $pattern = '/\bT(\d+)\b/';
        break;
      case PhabricatorPHIDConstants::PHID_TYPE_DREV:
        $pattern = '/\bD(\d+)\b/';
        break;
    }

    if (!$pattern) {
      return array();
    }

    $matches = array();
    preg_match_all($pattern, $query, $matches);
    if (!$matches) {
      return array();
    }

    $object_ids = $matches[1];
    if (!$object_ids) {
      return array();
    }

    switch ($this->type) {
      case PhabricatorPHIDConstants::PHID_TYPE_DREV:
        $objects = id(new DifferentialRevision())->loadAllWhere(
          'id IN (%Ld)',
          $object_ids);
        break;
      case PhabricatorPHIDConstants::PHID_TYPE_TASK:
        $objects = id(new ManiphestTask())->loadAllWhere(
          'id IN (%Ld)',
          $object_ids);
        break;
    }

    return array_fill_keys(mpull($objects, 'getPHID'), true);
  }

}
