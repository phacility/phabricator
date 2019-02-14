<?php

final class PhabricatorTokensSettingsPanel extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'tokens';
  }

  public function getPanelName() {
    return pht('Temporary Tokens');
  }

  public function getPanelMenuIcon() {
    return 'fa-ticket';
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsLogsPanelGroup::PANELGROUPKEY;
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
            'class' => 'small button button-grey',
            'sigil' => 'workflow',
          ),
          pht('Revoke'));
      } else {
        $button = javelin_tag(
          'a',
          array(
            'class' => 'small button button-grey disabled',
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

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-warning')
      ->setText(pht('Revoke All'))
      ->setHref('/auth/token/revoke/all/')
      ->setWorkflow(true)
      ->setColor(PHUIButtonView::RED);

    return $this->newBox(pht('Temporary Tokens'), $table, array($button));
  }

}
