<?php

final class PhabricatorSettingsPanelSessions
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'sessions';
  }

  public function getPanelName() {
    return pht('Sessions');
  }

  public function getPanelGroup() {
    return pht('Authentication');
  }

  public function isEnabled() {
    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->execute();

    $identity_phids = mpull($accounts, 'getPHID');
    $identity_phids[] = $viewer->getPHID();

    $sessions = id(new PhabricatorAuthSessionQuery())
      ->setViewer($viewer)
      ->withIdentityPHIDs($identity_phids)
      ->execute();

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($identity_phids)
      ->execute();

    // TODO: Once this has a real ID column, use that instead.
    $sessions = msort($sessions, 'getSessionStart');
    $sessions = array_reverse($sessions);

    $current_key = PhabricatorHash::digest($request->getCookie('phsid'));

    $rows = array();
    $rowc = array();
    foreach ($sessions as $session) {
      if ($session->getSessionKey() == $current_key) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = null;
      }

      $rows[] = array(
        $handles[$session->getUserPHID()]->renderLink(),
        substr($session->getSessionKey(), 0, 12),
        $session->getType(),
        phabricator_datetime($session->getSessionStart(), $viewer),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString(pht("You don't have any active sessions."));
    $table->setRowClasses($rowc);
    $table->setHeaders(
      array(
        pht('Identity'),
        pht('Session'),
        pht('Type'),
        pht('Created'),
      ));
    $table->setColumnClasses(
      array(
        'wide',
        'n',
        '',
        'right',
      ));


    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Active Login Sessions'));

    $panel = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($table);

    return $panel;
  }

}
