<?php

final class PhabricatorConduitTokensSettingsPanel
  extends PhabricatorSettingsPanel {

  public function isManagementPanel() {
    if ($this->getUser()->getIsMailingList()) {
      return false;
    }

    return true;
  }

  public function getPanelKey() {
    return 'apitokens';
  }

  public function getPanelName() {
    return pht('Conduit API Tokens');
  }

  public function getPanelMenuIcon() {
    return id(new PhabricatorConduitApplication())->getIcon();
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsLogsPanelGroup::PANELGROUPKEY;
  }

  public function isEnabled() {
    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $user = $this->getUser();

    $tokens = id(new PhabricatorConduitTokenQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($user->getPHID()))
      ->withExpired(false)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();

    $rows = array();
    foreach ($tokens as $token) {
      $rows[] = array(
        javelin_tag(
          'a',
          array(
            'href' => '/conduit/token/edit/'.$token->getID().'/',
            'sigil' => 'workflow',
          ),
          $token->getPublicTokenName()),
        PhabricatorConduitToken::getTokenTypeName($token->getTokenType()),
        phabricator_datetime($token->getDateCreated(), $viewer),
        ($token->getExpires()
          ? phabricator_datetime($token->getExpires(), $viewer)
          : pht('Never')),
        javelin_tag(
          'a',
          array(
            'class' => 'button small button-grey',
            'href' => '/conduit/token/terminate/'.$token->getID().'/',
            'sigil' => 'workflow',
          ),
          pht('Terminate')),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString(pht("You don't have any active API tokens."));
    $table->setHeaders(
      array(
        pht('Token'),
        pht('Type'),
        pht('Created'),
        pht('Expires'),
        null,
      ));
    $table->setColumnClasses(
      array(
        'wide pri',
        '',
        'right',
        'right',
        'action',
      ));

    $generate_button = id(new PHUIButtonView())
      ->setText(pht('Generate Token'))
      ->setHref('/conduit/token/edit/?objectPHID='.$user->getPHID())
      ->setTag('a')
      ->setWorkflow(true)
      ->setIcon('fa-plus');

    $terminate_button = id(new PHUIButtonView())
      ->setText(pht('Terminate Tokens'))
      ->setHref('/conduit/token/terminate/?objectPHID='.$user->getPHID())
      ->setTag('a')
      ->setWorkflow(true)
      ->setIcon('fa-exclamation-triangle')
      ->setColor(PHUIButtonView::RED);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Active API Tokens'))
      ->addActionLink($generate_button)
      ->addActionLink($terminate_button);

    $panel = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->appendChild($table);

    return $panel;
  }

}
