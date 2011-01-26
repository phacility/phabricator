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

class PhabricatorPHIDListController
  extends PhabricatorPHIDController {

  public function processRequest() {
    $items = id(new PhabricatorPHID())->loadAllWhere(
      '1 = 1 ORDER BY id DESC limit 100');

    $rows = array();
    foreach ($items as $item) {
      $rows[] = array(
        phutil_escape_html($item->getPHID()),
        phutil_escape_html($item->getPHIDType()),
        phutil_escape_html($item->getOwnerPHID()),
        phutil_escape_html($item->getParentPHID()),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'PHID',
        'Type',
        'Owner PHID',
        'Parent PHID',
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader('PHIDs');
    $panel->setCreateButton('Allocate New PHID', '/phid/new/');

    return $this->buildStandardPageResponse(
      array(
        $panel,
      ),
      array(
        'title' => 'PHIDs',
        'tab'   => 'phids',
      ));
  }

}
