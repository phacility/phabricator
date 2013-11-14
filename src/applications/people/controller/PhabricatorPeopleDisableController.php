<?php

final class PhabricatorPeopleDisableController
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
        ->disableUser($user, true);

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($admin)
      ->setTitle(pht('Confirm Disable'))
      ->appendChild(
        pht(
          'Disable %s? They will no longer be able to access Phabricator or '.
          'receive email.',
          phutil_tag('strong', array(), $user->getUsername())))
      ->addCancelButton($done_uri)
      ->addSubmitButton(pht('Disable Account'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
