<?php

final class PhabricatorSpacesNoAccessController
  extends PhabricatorSpacesController {

  public function handleRequest(AphrontRequest $request) {
    return $this->newDialog()
      ->setTitle(pht('No Access to Spaces'))
      ->appendParagraph(
        pht(
          'This install uses spaces to organize objects, but your account '.
          'does not have access to any spaces.'))
      ->appendParagraph(
        pht(
          'Ask someone to add you to a Space so you can view and create '.
          'objects.'))
      ->addCancelButton('/', pht('Drift Aimlessly'));
  }

}
