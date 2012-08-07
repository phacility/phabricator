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

final class HeraldRuleEditHistoryController extends HeraldController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();

    $edit_query = new HeraldEditLogQuery();
    if ($this->id) {
      $edit_query->withRuleIDs(array($this->id));
    }

    $pager = new AphrontPagerView();
    $pager->setURI($request->getRequestURI(), 'offset');
    $pager->setOffset($request->getStr('offset'));

    $edits = $edit_query->executeWithOffsetPager($pager);

    $need_phids = mpull($edits, 'getEditorPHID');
    $handles = id(new PhabricatorObjectHandleData($need_phids))
      ->loadHandles();

    $list_view = id(new HeraldRuleEditHistoryView())
      ->setEdits($edits)
      ->setHandles($handles)
      ->setUser($this->getRequest()->getUser());

    $panel = new AphrontPanelView();
    $panel->setHeader('Edit History');
    $panel->appendChild($list_view);

    $nav = $this->renderNav();
    $nav->selectFilter('history');
    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Rule Edit History',
      ));
  }

}
