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

final class PhabricatorMetaMTAReceivedListController
  extends PhabricatorMetaMTAController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));
    $pager->setURI($request->getRequestURI(), 'page');

    $mails = id(new PhabricatorMetaMTAReceivedMail())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize() + 1);
    $mails = $pager->sliceResults($mails);

    $phids = array_merge(
      mpull($mails, 'getAuthorPHID'),
      mpull($mails, 'getRelatedPHID')
    );
    $phids = array_unique(array_filter($phids));

    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    $rows = array();
    foreach ($mails as $mail) {
      $rows[] = array(
        $mail->getID(),
        phabricator_date($mail->getDateCreated(), $user),
        phabricator_time($mail->getDateCreated(), $user),
        $mail->getAuthorPHID()
          ? $handles[$mail->getAuthorPHID()]->renderLink()
          : '-',
        $mail->getRelatedPHID()
          ? $handles[$mail->getRelatedPHID()]->renderLink()
          : '-',
        phutil_escape_html($mail->getMessage()),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'ID',
        'Date',
        'Time',
        'Author',
        'Object',
        'Message',
      ));
    $table->setColumnClasses(
      array(
        null,
        null,
        'right',
        null,
        null,
        'wide',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Received Mail');
    $panel->appendChild($table);
    $panel->appendChild($pager);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('received');
    $nav->appendChild($panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => 'Received Mail',
      ));
  }
}
