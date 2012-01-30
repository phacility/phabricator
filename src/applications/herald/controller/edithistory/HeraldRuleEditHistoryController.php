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

class HeraldRuleEditHistoryController extends HeraldController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $rule = id(new HeraldRule())->load($this->id);
    if ($rule === null) {
      return new Aphront404Response();
    }

    $edits = $rule->loadEdits();
    $rule->attachEdits($edits);

    $need_phids = mpull($edits, 'getEditorPHID');
    $handles = id(new PhabricatorObjectHandleData($need_phids))
      ->loadHandles();

    $list_view = id(new HeraldRuleEditHistoryView())
      ->setRule($rule)
      ->setHandles($handles)
      ->setUser($this->getRequest()->getUser());

    return $this->buildStandardPageResponse(
      $list_view->render(),
      array(
        'title' => 'Rule Edit History',
      ));
  }

  public function getFilter() {
    return;
  }
}
