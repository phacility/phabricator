<?php

final class PhabricatorPeopleDeleteController
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

    if ($user->getPHID() == $admin->getPHID()) {
      return $this->buildDeleteSelfResponse($profile_uri);
    }

    $errors = array();

    $v_username = '';
    $e_username = true;
    if ($request->isFormPost()) {
      $v_username = $request->getStr('username');

      if (!strlen($v_username)) {
        $errors[] = pht(
          'You must type the username to confirm that you want to delete '.
          'this user account.');
        $e_username = pht('Required');
      } else if ($v_username != $user->getUsername()) {
        $errors[] = pht(
          'You must type the username correctly to confirm that you want '.
          'to delete this user account.');
        $e_username = pht('Incorrect');
      }

      if (!$errors) {
        id(new PhabricatorUserEditor())
          ->setActor($admin)
          ->deleteUser($user);

        $done_uri = $this->getApplicationURI();

        return id(new AphrontRedirectResponse())->setURI($done_uri);
      }
    }

    $str1 = pht(
      'Be careful when deleting users! This will permanently and '.
      'irreversibly destroy this user account.');

    $str2 = pht(
      'If this user interacted with anything, it is generally better to '.
      'disable them, not delete them. If you delete them, it will no longer '.
      'be possible to (for example) search for objects they created, and you '.
      'will lose other information about their history. Disabling them '.
      'instead will prevent them from logging in but not destroy any of '.
      'their data.');

    $str3 = pht(
      'It is generally safe to delete newly created users (and test users and '.
      'so on), but less safe to delete established users. If possible, '.
      'disable them instead.');

    $form = id(new AphrontFormView())
      ->setUser($admin)
      ->appendRemarkupInstructions(
        pht(
          'To confirm that you want to permanently and irrevocably destroy '.
          'this user account, type their username:'))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Username'))
          ->setValue($user->getUsername()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Confirm'))
          ->setValue($v_username)
          ->setName('username')
          ->setError($e_username));

    if ($errors) {
      $errors = id(new AphrontErrorView())->setErrors($errors);
    }

    return $this->newDialog()
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle(pht('Really Delete User?'))
      ->setShortTitle(pht('Delete User'))
      ->appendChild($errors)
      ->appendParagraph($str1)
      ->appendParagraph($str2)
      ->appendParagraph($str3)
      ->appendChild($form->buildLayoutView())
      ->addSubmitButton(pht('Delete User'))
      ->addCancelButton($profile_uri);
  }

  private function buildDeleteSelfResponse($profile_uri) {
    return $this->newDialog()
      ->setTitle(pht('You Shall Journey No Farther'))
      ->appendParagraph(
        pht(
          'As you stare into the gaping maw of the abyss, something '.
          'holds you back.'))
      ->appendParagraph(
        pht(
          'You can not delete your own account.'))
      ->addCancelButton($profile_uri, pht('Turn Back'));
  }


}
