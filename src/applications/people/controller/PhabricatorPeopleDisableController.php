<?php

final class PhabricatorPeopleDisableController
  extends PhabricatorPeopleController {

  public function shouldRequireAdmin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');
    $via = $request->getURIData('via');

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    // NOTE: We reach this controller via the administrative "Disable User"
    // on profiles and also via the "X" action on the approval queue. We do
    // things slightly differently depending on the context the actor is in.

    // In particular, disabling via "Disapprove" requires you be an
    // administrator (and bypasses the "Can Disable Users" permission).
    // Disabling via "Disable" requires the permission only.

    $is_disapprove = ($via == 'disapprove');
    if ($is_disapprove) {
      $done_uri = $this->getApplicationURI('query/approval/');

      if (!$viewer->getIsAdmin()) {
        return $this->newDialog()
          ->setTitle(pht('No Permission'))
          ->appendParagraph(pht('Only administrators can disapprove users.'))
          ->addCancelButton($done_uri);
      }

      if ($user->getIsApproved()) {
        return $this->newDialog()
          ->setTitle(pht('Already Approved'))
          ->appendParagraph(pht('This user has already been approved.'))
          ->addCancelButton($done_uri);
      }

      // On the "Disapprove" flow, bypass the "Can Disable Users" permission.
      $actor = PhabricatorUser::getOmnipotentUser();
      $should_disable = true;
    } else {
      $this->requireApplicationCapability(
        PeopleDisableUsersCapability::CAPABILITY);

      $actor = $viewer;
      $done_uri = $this->getApplicationURI("manage/{$id}/");
      $should_disable = !$user->getIsDisabled();
    }

    if ($viewer->getPHID() == $user->getPHID()) {
      return $this->newDialog()
        ->setTitle(pht('Something Stays Your Hand'))
        ->appendParagraph(
          pht(
            'Try as you might, you find you can not disable your own account.'))
        ->addCancelButton($done_uri, pht('Curses!'));
    }

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorUserTransaction())
        ->setTransactionType(PhabricatorUserDisableTransaction::TRANSACTIONTYPE)
        ->setNewValue($should_disable);

      id(new PhabricatorUserTransactionEditor())
        ->setActor($actor)
        ->setActingAsPHID($viewer->getPHID())
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true)
        ->applyTransactions($user, $xactions);

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    if ($should_disable) {
      $title = pht('Disable User?');
      $short_title = pht('Disable User');

      $body = pht(
        'Disable %s? They will no longer be able to access this server or '.
        'receive email.',
        phutil_tag('strong', array(), $user->getUsername()));

      $submit = pht('Disable User');
    } else {
      $title = pht('Enable User?');
      $short_title = pht('Enable User');

      $body = pht(
        'Enable %s? They will be able to access this server and receive '.
        'email again.',
        phutil_tag('strong', array(), $user->getUsername()));

      $submit = pht('Enable User');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setShortTitle($short_title)
      ->appendParagraph($body)
      ->addCancelButton($done_uri)
      ->addSubmitButton($submit);
  }

}
