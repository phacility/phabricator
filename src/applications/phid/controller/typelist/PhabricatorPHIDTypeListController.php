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

class PhabricatorPHIDTypeListController
  extends PhabricatorPHIDController {

  public function processRequest() {
    $items = id(new PhabricatorPHIDType())->loadAll();

    $rows = array();
    foreach ($items as $item) {
      $rows[] = array(
        $item->getID(),
        phutil_escape_html($item->getType()),
        phutil_escape_html($item->getName()),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'ID',
        'Type Code',
        'Name',
      ));
    $table->setColumnClasses(
      array(
        null,
        null,
        'wide',
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader('PHID Types');
    $panel->setCreateButton('New Type', '/phid/type/edit/');

    return $this->buildStandardPageResponse($panel, array(
      'title' => 'PHID Types',
      'tab'   => 'types',
      ));
  }

}
