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

  public function setHistory(array $history) {
    $this->history = $history;
    return $this;
  }

  public function render() {
    $drequest = $this->getDiffusionRequest();

    $rows = array();
    foreach ($this->history as $history) {
      $rows[] = array(
        $this->linkBrowse(
          $drequest->getPath(),
          array(
            'commit' => $history->getCommitIdentifier(),
          )),
        self::linkCommit(
          $drequest->getRepository(),
          $history->getCommitIdentifier()),
        '?',
        '?',
        '',
        '',
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
        'Author',
        'Details',
      ));
    $view->setColumnClasses(
      array(
        '',
        'n',
        '',
        '',
        '',
        'wide wrap',
      ));
    return $view->render();
  }

}
