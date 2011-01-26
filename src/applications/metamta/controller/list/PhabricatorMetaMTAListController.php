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

class PhabricatorMetaMTAListController extends PhabricatorMetaMTAController {

  public function processRequest() {
    $mails = id(new PhabricatorMetaMTAMail())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT 100');

    $rows = array();
    foreach ($mails as $mail) {
      $rows[] = array(
        $mail->getID(),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'ID',
      ));
    $table->setColumnClasses(
      array(
        null,
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader('MetaMTA Messages');
    $panel->setCreateButton('Send New Message', '/mail/send/');

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'MetaMTA',
      ));
  }
}
