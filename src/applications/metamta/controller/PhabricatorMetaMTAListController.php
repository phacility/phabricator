<?php

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
      $next_retry = $mail->getNextRetry() - time();
      if ($next_retry <= 0) {
        $next_retry = "None";
      } else {
        $next_retry = phabricator_format_relative_time_detailed($next_retry);
      }

      $rows[] = array(
        PhabricatorMetaMTAMail::getReadableStatus($mail->getStatus()),
        $mail->getRetryCount(),
        $next_retry,
        phabricator_datetime($mail->getDateCreated(), $user),
        phabricator_format_relative_time_detailed(
          time() - $mail->getDateModified()),
        phutil_escape_html($mail->getSubject()),
        phutil_render_tag(
          'a',
          array(
            'class' => 'button small grey',
            'href'  => $this->getApplicationURI('/view/'.$mail->getID().'/'),
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
    $panel->appendChild($pager);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('sent');
    $nav->appendChild($panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => 'Sent Mail',
      ));
  }
}
