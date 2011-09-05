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

class DiffusionSymbolController extends DiffusionController {

  private $name;

  public function willProcessRequest(array $data) {
    $this->name = $data['name'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = new DiffusionSymbolQuery();
    $query->setNamePrefix($this->name);

    if ($request->getStr('type')) {
      $query->setType($request->getStr('type'));
    }

    if ($request->getStr('lang')) {
      $query->setLanguage($request->getStr('lang'));
    }

    $symbols = $query->execute();

    $rows = array();
    foreach ($symbols as $symbol) {
      $rows[] = array(
        phutil_escape_html($symbol->getSymbolType()),
        phutil_escape_html($symbol->getSymbolName()),
        phutil_escape_html($symbol->getSymbolLanguage()),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Type',
        'Name',
        'Language',
      ));
    $table->setColumnClasses(
      array(
        '',
        'pri',
        '',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Similar Symbols');
    $panel->appendChild($table);

    return $this->buildStandardPageResponse(
      array(
        $panel,
      ),
      array(
        'title' => 'Find Symbol',
      ));
  }

}
