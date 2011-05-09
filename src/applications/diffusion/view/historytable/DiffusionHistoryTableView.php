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

final class DiffusionHistoryTableView extends DiffusionView {

  private $history;
  private $handles = array();

  public function setHistory(array $history) {
    $this->history = $history;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function render() {
    $drequest = $this->getDiffusionRequest();

    $handles = $this->handles;

    $rows = array();
    foreach ($this->history as $history) {
      $epoch = $history->getEpoch();

      if ($epoch) {
        $date = date('M j, Y', $epoch);
        $time = date('g:i A', $epoch);
      } else {
        $date = null;
        $time = null;
      }

      $data = $history->getCommitData();
      $author_phid = null;
      if ($data) {
        $author_phid = $data->getCommitDetail('authorPHID');
      }

      if ($author_phid && isset($handles[$author_phid])) {
        $author = $handles[$author_phid]->renderLink();
      } else {
        $author = phutil_escape_html($history->getAuthorName());
      }

      $rows[] = array(
        $this->linkBrowse(
          $drequest->getPath(),
          array(
            'commit' => $history->getCommitIdentifier(),
          )),
        self::linkCommit(
          $drequest->getRepository(),
          $history->getCommitIdentifier()),
        $this->linkChange(
          $history->getChangeType(),
          $history->getFileType(),
          null,
          $history->getCommitIdentifier()),
        $date,
        $time,
        $author,
        phutil_escape_html($history->getSummary()),
        // TODO: etc etc
      );
    }

    $view = new AphrontTableView($rows);
    $view->setHeaders(
      array(
        'Browse',
        'Commit',
        'Change',
        'Date',
        'Time',
        'Author',
        'Details',
      ));
    $view->setColumnClasses(
      array(
        '',
        'n',
        '',
        '',
        'right',
        '',
        'wide wrap',
      ));
    return $view->render();
  }

}
