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

class DiffusionHistoryController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->diffusionRequest;

    $history_query = DiffusionHistoryQuery::newFromDiffusionRequest(
      $drequest);

    $history = $history_query->loadHistory();

    $content = array();

    $history_table = new DiffusionHistoryTableView();
    $history_table->setDiffusionRequest($drequest);
    $history_table->setHistory($history);

    $history_panel = new AphrontPanelView();
    $history_panel->setHeader($drequest->getPath());
    $history_panel->appendChild($history_table);

    $content[] = $history_panel;

    // TODO: Crumbs
    // TODO: Side nav

    return $this->buildStandardPageResponse(
      $content,
      array(
        'title' => 'history',
      ));
  }

}
