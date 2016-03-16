<?php

final class PhabricatorTokensSettingsPanel extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'tokens';
  }

  public function getPanelName() {
    return pht('Temporary Tokens');
  }

  public function getPanelGroup() {
    return pht('Sessions and Logs');
  }

  public function isEnabled() {
    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $tokens = id(new PhabricatorAuthTemporaryTokenQuery())
      ->setViewer($viewer)
      ->withTokenResources(array($viewer->getPHID()))
      ->execute();

    $rows = array();
    foreach ($tokens as $token) {

      if ($token->isRevocable()) {
        $button = javelin_tag(
          'a',
          array(
            'href' => '/auth/token/revoke/'.$token->getID().'/',
            'class' => 'small grey button',
            'sigil' => 'workflow',
          ),
          pht('Revoke'));
      } else {
        $button = javelin_tag(
          'a',
          array(
            'class' => 'small grey button disabled',
          ),
          pht('Revoke'));
      }

      if ($token->getTokenExpires() >= time()) {
        $expiry = phabricator_datetime($token->getTokenExpires(), $viewer);
      } else {
        $expiry = pht('Expired');
      }

      $rows[] = array(
        $token->getTokenReadableTypeName(),
        $expiry,
        $button,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString(pht("You don't have any active tokens."));
    $table->setHeaders(
      array(
        pht('Type'),
        pht('Expires'),
        pht(''),
      ));
    $table->setColumnClasses(
      array(
        'wide',
        'right',
        'action',
      ));

    $terminate_button = id(new PHUIButtonView())
      ->setText(pht('Revoke All'))
      ->setHref('/auth/token/revoke/all/')
      ->setTag('a')
      ->setWorkflow(true)
      ->setIcon('fa-exclamation-triangle');

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Temporary Tokens'))
      ->addActionLink($terminate_button);

    $panel = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setTable($table);

    return $panel;
  }

}
