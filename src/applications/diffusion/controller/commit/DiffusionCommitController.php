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

    $repository = $drequest->getRepository();
    $commit = $drequest->loadCommit();

    if (!$commit) {
      // TODO: Make more user-friendly.
      throw new Exception('This commit has not parsed yet.');
    }

    $commit_data = $drequest->loadCommitData();

    require_celerity_resource('diffusion-commit-view-css');

    $detail_panel->appendChild(
      '<div class="diffusion-commit-view">'.
        '<div class="diffusion-commit-dateline">'.
          'r'.$repository->getCallsign().$commit->getCommitIdentifier().
          ' &middot; '.
          date('F jS, Y g:i A', $commit->getEpoch()).
        '</div>'.
        '<h1>Revision Detail</h1>'.
        '<div class="diffusion-commit-details">'.
          '<table class="diffusion-commit-properties">'.
            '<tr>'.
              '<th>Author:</th>'.
              '<td>'.phutil_escape_html($commit_data->getAuthorName()).'</td>'.
            '</tr>'.
          '</table>'.
          '<hr />'.
          '<div class="diffusion-commit-message">'.
            phutil_escape_html($commit_data->getCommitMessage()).
          '</div>'.
        '</div>'.
      '</div>');

    $content[] = $detail_panel;

    $change_query = DiffusionPathChangeQuery::newFromDiffusionRequest(
      $drequest);
    $changes = $change_query->loadChanges();

    $change_table = new DiffusionCommitChangeTableView();
    $change_table->setDiffusionRequest($drequest);
    $change_table->setPathChanges($changes);

    // TODO: Large number of modified files check.

    $count = number_format(count($changes));

    $bad_commit = null;
    if ($count == 0) {
      $bad_commit = queryfx_one(
        id(new PhabricatorRepository())->establishConnection('r'),
        'SELECT * FROM %T WHERE fullCommitName = %s',
        PhabricatorRepository::TABLE_BADCOMMIT,
        'r'.$repository->getCallsign().$commit->getCommitIdentifier());
    }

    if ($bad_commit) {
      $error_panel = new AphrontErrorView();
      $error_panel->setWidth(AphrontErrorView::WIDTH_WIDE);
      $error_panel->setTitle('Bad Commit');
      $error_panel->appendChild(
        phutil_escape_html($bad_commit['description']));

      $content[] = $error_panel;
    } else {
      $change_panel = new AphrontPanelView();
      $change_panel->setHeader("Changes ({$count})");
      $change_panel->appendChild($change_table);

      $content[] = $change_panel;

      $change_list =
        '<div style="margin: 2em; color: #666; padding: 1em;
          background: #eee;">'.
          '(list of changes goes here)'.
        '</div>';

      $content[] = $change_list;
    }

    return $this->buildStandardPageResponse(
      $content,
      array(
        'title' => 'Diffusion',
      ));
  }

}
