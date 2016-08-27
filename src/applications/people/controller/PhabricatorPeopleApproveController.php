<?php

final class PhabricatorPeopleApproveController
  extends PhabricatorPeopleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $done_uri = $this->getApplicationURI('query/approval/');

    if ($request->isFormPost()) {
      id(new PhabricatorUserEditor())
        ->setActor($viewer)
        ->approveUser($user, true);

      $title = pht(
        'Phabricator Account "%s" Approved',
        $user->getUsername());

      $body = sprintf(
        "%s\n\n  %s\n\n",
        pht(
          'Your Phabricator account (%s) has been approved by %s. You can '.
          'login here:',
          $user->getUsername(),
          $viewer->getUsername()),
        PhabricatorEnv::getProductionURI('/'));

      $mail = id(new PhabricatorMetaMTAMail())
        ->addTos(array($user->getPHID()))
        ->addCCs(array($viewer->getPHID()))
        ->setSubject('[Phabricator] '.$title)
        ->setForceDelivery(true)
        ->setBody($body)
        ->saveAndSend();

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Confirm Approval'))
      ->appendChild(
        pht(
          'Allow %s to access this Phabricator install?',
          phutil_tag('strong', array(), $user->getUsername())))
      ->addCancelButton($done_uri)
      ->addSubmitButton(pht('Approve Account'));
  }
}
