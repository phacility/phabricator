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

    if ($user->getIsApproved()) {
      return $this->newDialog()
        ->setTitle(pht('Already Approved'))
        ->appendChild(pht('This user has already been approved.'))
        ->addCancelButton($done_uri);
    }

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorUserTransaction())
        ->setTransactionType(PhabricatorUserApproveTransaction::TRANSACTIONTYPE)
        ->setNewValue(true);

      id(new PhabricatorUserTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true)
        ->applyTransactions($user, $xactions);

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
