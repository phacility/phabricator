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

class HeraldHomeController extends HeraldController {

  private $view;
  private $filter;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
    $this->setFilter($this->view);
  }

  public function getFilter() {
    return $this->filter;
  }
  public function setFilter($filter) {
    $this->filter = 'view/'.$filter;
    return $this;
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $map = HeraldContentTypeConfig::getContentTypeMap();
    if (empty($map[$this->view])) {
      reset($map);
      $this->view = key($map);
    }

    $rules = id(new HeraldRule())->loadAllWhere(
      'contentType = %s AND authorPHID = %s',
      $this->view,
      $user->getPHID());

    $need_phids = mpull($rules, 'getAuthorPHID');
    $handles = id(new PhabricatorObjectHandleData($need_phids))
      ->loadHandles();

    $list_view = id(new HeraldRuleListView())
      ->setRules($rules)
      ->setHandles($handles)
      ->setMap($map)
      ->setAllowCreation(true)
      ->setView($this->view);
    $panel = $list_view->render();


    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Herald',
      ));
  }
}
