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

final class PhabricatorXHProfSampleListController
  extends PhabricatorXHProfController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = $data['view'];
  }

  public function processRequest() {
    $request = $this->getRequest();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));

    switch ($this->view) {
      case 'sampled':
        $clause = '`sampleRate` > 0';
        $show_type = false;
        break;
      case 'my-runs':
        $clause = qsprintf(
          id(new PhabricatorXHProfSample())->establishConnection('r'),
          '`sampleRate` = 0 AND `userPHID` = %s',
          $request->getUser()->getPHID());
        $show_type = false;
        break;
      case 'manual':
        $clause = '`sampleRate` = 0';
        $show_type = false;
        break;
      case 'all':
      default:
        $clause = '1 = 1';
        $show_type = true;
        break;
    }

    $samples = id(new PhabricatorXHProfSample())->loadAllWhere(
      '%Q ORDER BY dateCreated DESC LIMIT %d, %d',
      $clause,
      $pager->getOffset(),
      $pager->getPageSize() + 1);

    $samples = $pager->sliceResults($samples);
    $pager->setURI($request->getRequestURI(), 'page');

    $table = new PhabricatorXHProfSampleListView();
    $table->setUser($request->getUser());
    $table->setSamples($samples);
    $table->setShowType($show_type);

    $panel = new AphrontPanelView();
    $panel->setHeader('XHProf Samples');
    $panel->appendChild($table);
    $panel->appendChild($pager);

    return $this->buildStandardPageResponse(
      $panel,
      array('title' => 'XHProf Samples'));

  }
}
