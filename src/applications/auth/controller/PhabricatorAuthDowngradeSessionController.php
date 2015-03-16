<?php

final class PhabricatorAuthDowngradeSessionController
  extends PhabricatorAuthController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $panel_uri = '/settings/panel/sessions/';

    $session = $viewer->getSession();
    if ($session->getHighSecurityUntil() < time()) {
      return $this->newDialog()
        ->setTitle(pht('Normal Security Restored'))
        ->appendParagraph(
          pht('Your session is no longer in high security.'))
        ->addCancelButton($panel_uri, pht('Continue'));
    }

    if ($request->isFormPost()) {

      id(new PhabricatorAuthSessionEngine())
        ->exitHighSecurity($viewer, $session);

      return id(new AphrontRedirectResponse())
        ->setURI($this->getApplicationURI('session/downgrade/'));
    }

    return $this->newDialog()
      ->setTitle(pht('Leaving High Security'))
      ->appendParagraph(
        pht(
          'Leave high security and return your session to normal '.
          'security levels?'))
      ->appendParagraph(
        pht(
          'If you leave high security, you will need to authenticate '.
          'again the next time you try to take a high security action.'))
      ->appendParagraph(
        pht(
          'On the plus side, that purple notification bubble will '.
          'disappear.'))
      ->addSubmitButton(pht('Leave High Security'))
      ->addCancelButton($panel_uri, pht('Stay'));
  }


}
