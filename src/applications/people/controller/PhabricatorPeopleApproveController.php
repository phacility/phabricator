<?php

final class PhabricatorPeopleApproveController
  extends PhabricatorPeopleController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $admin = $request->getUser();

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($admin)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $done_uri = $this->getApplicationURI('query/approval/');

    if ($request->isFormPost()) {
      id(new PhabricatorUserEditor())
        ->setActor($admin)
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
          $admin->getUsername()),
        PhabricatorEnv::getProductionURI('/'));

      $mail = id(new PhabricatorMetaMTAMail())
        ->addTos(array($user->getPHID()))
        ->addCCs(array($admin->getPHID()))
        ->setSubject('[Phabricator] '.$title)
        ->setForceDelivery(true)
        ->setBody($body)
        ->saveAndSend();

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($admin)
      ->setTitle(pht('Confirm Approval'))
      ->appendChild(
        pht(
          'Allow %s to access this Phabricator install?',
          phutil_tag('strong', array(), $user->getUsername())))
      ->addCancelButton($done_uri)
      ->addSubmitButton(pht('Approve Account'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
