<?php

final class PhabricatorPeopleEmpowerController
  extends PhabricatorPeopleController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
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

    $profile_uri = '/p/'.$user->getUsername().'/';

    id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $admin,
      $request,
      $profile_uri);

    if ($user->getPHID() == $admin->getPHID()) {
      return $this->newDialog()
        ->setTitle(pht('Your Way is Blocked'))
        ->appendParagraph(
          pht(
            'After a time, your efforts fail. You can not adjust your own '.
            'status as an administrator.'))
        ->addCancelButton($profile_uri, pht('Accept Fate'));
    }

    if ($request->isFormPost()) {
      id(new PhabricatorUserEditor())
        ->setActor($admin)
        ->makeAdminUser($user, !$user->getIsAdmin());

      return id(new AphrontRedirectResponse())->setURI($profile_uri);
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
      ->addCancelButton($profile_uri)
      ->addSubmitButton($submit);
  }

}
