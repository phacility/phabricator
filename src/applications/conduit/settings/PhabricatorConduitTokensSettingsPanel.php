<?php

final class PhabricatorConduitTokensSettingsPanel
  extends PhabricatorSettingsPanel {

  public function isEditableByAdministrators() {
    return true;
  }

  public function getPanelKey() {
    return 'apitokens';
  }

  public function getPanelName() {
    return pht('Conduit API Tokens');
  }

  public function getPanelGroup() {
    return pht('Sessions and Logs');
  }

  public function isEnabled() {
    if ($this->getUser()->getIsMailingList()) {
      return false;
    }

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
            'class' => 'button small grey',
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

    $generate_icon = id(new PHUIIconView())
      ->setIconFont('fa-plus');
    $generate_button = id(new PHUIButtonView())
      ->setText(pht('Generate API Token'))
      ->setHref('/conduit/token/edit/?objectPHID='.$user->getPHID())
      ->setTag('a')
      ->setWorkflow(true)
      ->setIcon($generate_icon);

    $terminate_icon = id(new PHUIIconView())
      ->setIconFont('fa-exclamation-triangle');
    $terminate_button = id(new PHUIButtonView())
      ->setText(pht('Terminate All Tokens'))
      ->setHref('/conduit/token/terminate/?objectPHID='.$user->getPHID())
      ->setTag('a')
      ->setWorkflow(true)
      ->setIcon($terminate_icon);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Active API Tokens'))
      ->addActionLink($generate_button)
      ->addActionLink($terminate_button);

    $panel = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setTable($table);

    return $panel;
  }

}
