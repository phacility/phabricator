<?php

final class PhabricatorPeopleRenameController
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

    $errors = array();

    $v_username = $user->getUsername();
    $e_username = true;
    if ($request->isFormPost()) {
      $v_username = $request->getStr('username');

      if (!strlen($v_username)) {
        $e_username = pht('Required');
        $errors[] = pht('New username is required.');
      } else if ($v_username == $user->getUsername()) {
        $e_username = pht('Invalid');
        $errors[] = pht('New username must be different from old username.');
      } else if (!PhabricatorUser::validateUsername($v_username)) {
        $e_username = pht('Invalid');
        $errors[] = PhabricatorUser::describeValidUsername();
      }

      if (!$errors) {
        try {
          id(new PhabricatorUserEditor())
            ->setActor($viewer)
            ->changeUsername($user, $v_username);

          return id(new AphrontRedirectResponse())->setURI($done_uri);
        } catch (AphrontDuplicateKeyQueryException $ex) {
          $e_username = pht('Not Unique');
          $errors[] = pht('Another user already has that username.');
        }
      }
    }

    $inst1 = pht(
      'Be careful when renaming users!');

    $inst2 = pht(
      'The old username will no longer be tied to the user, so anything '.
      'which uses it (like old commit messages) will no longer associate '.
      'correctly. (And, if you give a user a username which some other user '.
      'used to have, username lookups will begin returning the wrong user.)');

    $inst3 = pht(
      'It is generally safe to rename newly created users (and test users '.
      'and so on), but less safe to rename established users and unsafe to '.
      'reissue a username.');

    $inst4 = pht(
      'Users who rely on password authentication will need to reset their '.
      'password after their username is changed (their username is part of '.
      'the salt in the password hash).');

    $inst5 = pht(
      'The user will receive an email notifying them that you changed their '.
      'username, with instructions for logging in and resetting their '.
      'password if necessary.');

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Old Username'))
          ->setValue($user->getUsername()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('New Username'))
          ->setValue($v_username)
          ->setName('username')
          ->setError($e_username));

    if ($errors) {
      $errors = id(new PHUIInfoView())->setErrors($errors);
    }

    return $this->newDialog()
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle(pht('Change Username'))
      ->appendChild($errors)
      ->appendParagraph($inst1)
      ->appendParagraph($inst2)
      ->appendParagraph($inst3)
      ->appendParagraph($inst4)
      ->appendParagraph($inst5)
      ->appendParagraph(null)
      ->appendForm($form)
      ->addSubmitButton(pht('Rename User'))
      ->addCancelButton($done_uri);
  }

}
