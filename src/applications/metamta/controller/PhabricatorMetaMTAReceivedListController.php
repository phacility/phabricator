<?php

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
      mpull($mails, 'getRelatedPHID'));
    $phids = array_unique(array_filter($phids));

    $handles = $this->loadViewerHandles($phids);

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
        $mail->getMessage(),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('ID'),
        pht('Date'),
        pht('Time'),
        pht('Author'),
        pht('Object'),
        pht('Message'),
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
    $panel->setHeader(pht('Received Mail'));
    $panel->appendChild($table);
    $panel->appendChild($pager);
    $panel->setNoBackground();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('received');
    $nav->appendChild($panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Received Mail'),
        'device' => true,
      ));
  }
}
