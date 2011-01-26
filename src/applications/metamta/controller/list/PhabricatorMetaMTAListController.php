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
        PhabricatorMetaMTAMail::getReadableStatus($mail->getStatus()),
        $mail->getRetryCount(),
        ($mail->getNextRetry() - time()).' s',
        date('Y-m-d g:i:s A', $mail->getDateCreated()),
        (time() - $mail->getDateModified()).' s',
        phutil_escape_html($mail->getSubject()),
        phutil_render_tag(
          'a',
          array(
            'class' => 'button small grey',
            'href'  => '/mail/'.$mail->getID().'/',
          ),
          'View'),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Status',
        'Retry',
        'Next',
        'Created',
        'Updated',
        'Subject',
        '',
      ));
    $table->setColumnClasses(
      array(
        null,
        null,
        null,
        null,
        null,
        'wide',
        'action',
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
