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

class DiffusionCommitController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();

    $content = array();
    $content[] = $this->buildCrumbs(array(
      'commit' => true,
    ));

    $detail_panel = new AphrontPanelView();
    $detail_panel->setHeader('Revision Detail');
    $detail_panel->appendChild('<div>'.$drequest->getCommit().'</div>');

    $content[] = $detail_panel;

    $change_query = DiffusionPathChangeQuery::newFromDiffusionRequest(
      $drequest);
    $changes = $change_query->loadChanges();

    $change_table = new DiffusionCommitChangeTableView();
    $change_table->setDiffusionRequest($drequest);
    $change_table->setPathChanges($changes);

    $change_panel = new AphrontPanelView();
    $change_panel->setHeader('Changes');
    $change_panel->appendChild($change_table);

    $content[] = $change_panel;


    $change_list =
      '<div style="margin: 2em; color: #666; padding: 1em; background: #eee;">'.
        '(list of changes goes here)'.
      '</div>';

    $content[] = $change_list;

    return $this->buildStandardPageResponse(
      $content,
      array(
        'title' => 'Diffusion',
      ));
  }

}
