<?php

final class PhabricatorSessionsSettingsPanel extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'sessions';
  }

  public function getPanelName() {
    return pht('Sessions');
  }

  public function getPanelGroup() {
    return pht('Sessions and Logs');
  }

  public function isEnabled() {
    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $accounts = id(new PhabricatorExternalAccountQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
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
      $is_current = phutil_hashes_are_identical(
        $session->getSessionKey(),
        $current_key);
      if ($is_current) {
        $rowc[] = 'highlighted';
        $button = phutil_tag(
          'a',
          array(
            'class' => 'small grey button disabled',
          ),
          pht('Current'));
      } else {
        $rowc[] = null;
        $button = javelin_tag(
          'a',
          array(
            'href' => '/auth/session/terminate/'.$session->getID().'/',
            'class' => 'small grey button',
            'sigil' => 'workflow',
          ),
          pht('Terminate'));
      }

      $hisec = ($session->getHighSecurityUntil() - time());

      $rows[] = array(
        $handles[$session->getUserPHID()]->renderLink(),
        substr($session->getSessionKey(), 0, 6),
        $session->getType(),
        ($hisec > 0)
          ? phutil_format_relative_time($hisec)
          : null,
        phabricator_datetime($session->getSessionStart(), $viewer),
        phabricator_date($session->getSessionExpires(), $viewer),
        $button,
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
        pht('HiSec'),
        pht('Created'),
        pht('Expires'),
        pht(''),
      ));
    $table->setColumnClasses(
      array(
        'wide',
        'n',
        '',
        'right',
        'right',
        'right',
        'action',
      ));


    $terminate_icon = id(new PHUIIconView())
      ->setIconFont('fa-exclamation-triangle');
    $terminate_button = id(new PHUIButtonView())
      ->setText(pht('Terminate All Sessions'))
      ->setHref('/auth/session/terminate/all/')
      ->setTag('a')
      ->setWorkflow(true)
      ->setIcon($terminate_icon);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Active Login Sessions'))
      ->addActionLink($terminate_button);

    $hisec = ($viewer->getSession()->getHighSecurityUntil() - time());
    if ($hisec > 0) {
      $hisec_icon = id(new PHUIIconView())
        ->setIconFont('fa-lock');
      $hisec_button = id(new PHUIButtonView())
        ->setText(pht('Leave High Security'))
        ->setHref('/auth/session/downgrade/')
        ->setTag('a')
        ->setWorkflow(true)
        ->setIcon($hisec_icon);
      $header->addActionLink($hisec_button);
    }

    $panel = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setTable($table);

    return $panel;
  }

}
