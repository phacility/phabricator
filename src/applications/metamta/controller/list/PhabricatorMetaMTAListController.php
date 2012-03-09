<?php

/*
 * Copyright 2012 Facebook, Inc.
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

final class PhabricatorMetaMTAListController
  extends PhabricatorMetaMTAController {

  public function processRequest() {
    // Get a page of mails together with pager.
    $request = $this->getRequest();
    $user = $request->getUser();
    $offset = $request->getInt('offset', 0);
    $related_phid = $request->getStr('phid');
    $status = $request->getStr('status');

    $pager = new AphrontPagerView();
    $pager->setOffset($offset);
    $pager->setURI($request->getRequestURI(), 'offset');

    $mail = new PhabricatorMetaMTAMail();
    $conn_r = $mail->establishConnection('r');

    $wheres = array();
    if ($status) {
      $wheres[] = qsprintf(
        $conn_r,
        'status = %s',
        $status);
    }
    if ($related_phid) {
      $wheres[] = qsprintf(
        $conn_r,
        'relatedPHID = %s',
        $related_phid);
    }
    if (count($wheres)) {
      $where_clause = 'WHERE '.implode($wheres, ' AND ');
    } else {
      $where_clause = 'WHERE 1 = 1';
    }

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T
        %Q
        ORDER BY id DESC
        LIMIT %d, %d',
        $mail->getTableName(),
        $where_clause,
        $pager->getOffset(), $pager->getPageSize() + 1);
    $data = $pager->sliceResults($data);

    $mails = $mail->loadAllFromArray($data);

    // Render the details table.
    $rows = array();
    foreach ($mails as $mail) {
      $rows[] = array(
        PhabricatorMetaMTAMail::getReadableStatus($mail->getStatus()),
        $mail->getRetryCount(),
        ($mail->getNextRetry() - time()).' s',
        phabricator_datetime($mail->getDateCreated(), $user),
        (time() - $mail->getDateModified()).' s',
        phutil_escape_html($mail->getSubject()),
        phutil_render_tag(
          'a',
          array(
            'class' => 'button small grey',
            'href'  => '/mail/view/'.$mail->getID().'/',
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

    // Render the whole page.
    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader('MetaMTA Messages');
    if ($user->getIsAdmin()) {
      $panel->setCreateButton('Send New Test Message', '/mail/send/');
    }
    $panel->appendChild($pager);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'MetaMTA',
        'tab'   => 'queue',
      ));
  }
}
