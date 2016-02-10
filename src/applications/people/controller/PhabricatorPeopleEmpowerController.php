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

    id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $done_uri);

    if ($user->getPHID() == $viewer->getPHID()) {
      return $this->newDialog()
        ->setTitle(pht('Your Way is Blocked'))
        ->appendParagraph(
          pht(
            'After a time, your efforts fail. You can not adjust your own '.
            'status as an administrator.'))
        ->addCancelButton($done_uri, pht('Accept Fate'));
    }

    if ($request->isFormPost()) {
      id(new PhabricatorUserEditor())
        ->setActor($viewer)
        ->makeAdminUser($user, !$user->getIsAdmin());

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    if ($user->getIsAdmin()) {
      $title = pht('Remove as Administrator?');
      $short = pht('Remove Administrator');
      $body = pht(
        'Remove %s as an administrator? They will no longer be able to '.
        'perform administrative functions on this Phabricator install.',
        phutil_tag('strong', array(), $user->getUsername()));
      $submit = pht('Remove Administrator');
    } else {
      $title = pht('Make Administrator?');
      $short = pht('Make Administrator');
      $body = pht(
        'Empower %s as an administrator? They will be able to create users, '.
        'approve users, make and remove administrators, delete accounts, and '.
        'perform other administrative functions on this Phabricator install.',
        phutil_tag('strong', array(), $user->getUsername()));
      $submit = pht('Make Administrator');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setShortTitle($short)
      ->appendParagraph($body)
      ->addCancelButton($done_uri)
      ->addSubmitButton($submit);
  }

}
