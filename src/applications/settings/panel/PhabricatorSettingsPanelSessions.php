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

    $current_key = PhabricatorHash::digest(
      $request->getCookie(PhabricatorCookies::COOKIE_SESSION));

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
        phabricator_datetime($session->getSessionExpires(), $viewer),
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
        pht('Expires'),
      ));
    $table->setColumnClasses(
      array(
        'wide',
        'n',
        '',
        'right',
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
