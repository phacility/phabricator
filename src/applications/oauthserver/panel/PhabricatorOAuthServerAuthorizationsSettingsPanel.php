<?php

final class PhabricatorOAuthServerAuthorizationsSettingsPanel
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'oauthorizations';
  }

  public function getPanelName() {
    return pht('OAuth Authorizations');
  }

  public function getPanelGroup() {
    return pht('Sessions and Logs');
  }

  public function isEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorOAuthServerApplication');
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    // TODO: It would be nice to simply disable this panel, but we can't do
    // viewer-based checks for enabled panels right now.

    $app_class = 'PhabricatorOAuthServerApplication';
    $installed = PhabricatorApplication::isClassInstalledForViewer(
      $app_class,
      $viewer);
    if (!$installed) {
      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setTitle(pht('OAuth Not Available'))
        ->appendParagraph(
          pht('You do not have access to OAuth authorizations.'))
        ->addCancelButton('/settings/');
      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $authorizations = id(new PhabricatorOAuthClientAuthorizationQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->execute();
    $authorizations = mpull($authorizations, null, 'getID');

    $panel_uri = $this->getPanelURI();

    $revoke = $request->getInt('revoke');
    if ($revoke) {
      if (empty($authorizations[$revoke])) {
        return new Aphront404Response();
      }

      if ($request->isFormPost()) {
        $authorizations[$revoke]->delete();
        return id(new AphrontRedirectResponse())->setURI($panel_uri);
      }

      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setTitle(pht('Revoke Authorization?'))
        ->appendParagraph(
          pht(
            'This application will no longer be able to access Phabricator '.
            'on your behalf.'))
        ->addSubmitButton(pht('Revoke Authorization'))
        ->addCancelButton($panel_uri);

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $highlight = $request->getInt('id');

    $rows = array();
    $rowc = array();
    foreach ($authorizations as $authorization) {
      if ($highlight == $authorization->getID()) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = null;
      }

      $button = javelin_tag(
        'a',
        array(
          'href' => $this->getPanelURI('?revoke='.$authorization->getID()),
          'class' => 'small grey button',
          'sigil' => 'workflow',
        ),
        pht('Revoke'));

      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => $authorization->getClient()->getViewURI(),
          ),
          $authorization->getClient()->getName()),
        $authorization->getScopeString(),
        phabricator_datetime($authorization->getDateCreated(), $viewer),
        phabricator_datetime($authorization->getDateModified(), $viewer),
        $button,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString(
      pht("You haven't authorized any OAuth applications."));

    $table->setRowClasses($rowc);
    $table->setHeaders(
      array(
        pht('Application'),
        pht('Scope'),
        pht('Created'),
        pht('Updated'),
        null,
      ));

    $table->setColumnClasses(
      array(
        'pri',
        'wide',
        'right',
        'right',
        'action',
      ));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('OAuth Application Authorizations'));

    $panel = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($table);

    return $panel;
  }

}
