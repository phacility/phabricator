<?php

final class PhabricatorSpacesNoAccessController
  extends PhabricatorSpacesController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $dialog = $this->newDialog()
      ->setTitle(pht('No Access to Spaces'))
      ->addCancelButton('/', pht('Drift Aimlessly'));

    if ($viewer->isLoggedIn()) {
      $dialog
        ->appendParagraph(
          pht(
            'This install uses spaces to organize objects, but your account '.
            'does not have access to any spaces.'))
        ->appendParagraph(
          pht(
            'Ask someone to give you access to a space so you can view and '.
            'create objects.'));
    } else {
      // Tailor the message a bit for logged-out users.
      $dialog
        ->appendParagraph(
          pht(
            'This install uses spaces to organize objects, but logged out '.
            'users do not have access to any spaces.'))
        ->appendParagraph(
          pht(
            'Log in, or ask someone to create a public space which logged '.
            'out users are permitted to access.'))
        ->appendParagraph(
          pht(
            '(This error generally indicates that %s is enabled, but there '.
            'are no spaces with a "%s" view policy. These settings are '.
            'contradictory and imply a misconfiguration.)',
            phutil_tag('tt', array(), 'policy.allow-public'),
            pht('Public (No Login Required)')));
    }


    return $dialog;
  }

}
