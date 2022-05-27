<?php

final class PhabricatorPeopleEmpowerController
  extends PhabricatorPeopleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $done_uri = $this->getApplicationURI("manage/{$id}/");

    $validation_exception = null;
    if ($request->isFormOrHisecPost()) {
      $xactions = array();
      $xactions[] = id(new PhabricatorUserTransaction())
        ->setTransactionType(
          PhabricatorUserEmpowerTransaction::TRANSACTIONTYPE)
        ->setNewValue(!$user->getIsAdmin());

      $editor = id(new PhabricatorUserTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setCancelURI($done_uri);

      try {
        $editor->applyTransactions($user, $xactions);
        return id(new AphrontRedirectResponse())->setURI($done_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }
    }

    if ($user->getIsAdmin()) {
      $title = pht('Remove as Administrator?');
      $short = pht('Remove Administrator');
      $body = pht(
        'Remove %s as an administrator? They will no longer be able to '.
        'perform administrative functions on this server.',
        phutil_tag('strong', array(), $user->getUsername()));
      $submit = pht('Remove Administrator');
    } else {
      $title = pht('Make Administrator?');
      $short = pht('Make Administrator');
      $body = pht(
        'Empower %s as an administrator? They will be able to create users, '.
        'approve users, make and remove administrators, delete accounts, and '.
        'perform other administrative functions on this server.',
        phutil_tag('strong', array(), $user->getUsername()));
      $submit = pht('Make Administrator');
    }

    return $this->newDialog()
      ->setValidationException($validation_exception)
      ->setTitle($title)
      ->setShortTitle($short)
      ->appendParagraph($body)
      ->addCancelButton($done_uri)
      ->addSubmitButton($submit);
  }

}
