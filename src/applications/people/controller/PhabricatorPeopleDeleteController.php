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

    $str1 = pht(
      'Be careful when deleting users! This will permanently and '.
      'irreversibly destroy this user account.');

    $str2 = pht(
      'If this user interacted with anything, it is generally better to '.
      'disable them, not delete them. If you delete them, it will no longer '.
      'be possible to (for example) search for objects they created, and you '.
      'will lose other information about their history. Disabling them '.
      'instead will prevent them from logging in, but will not destroy any of '.
      'their data.');

    $str3 = pht(
      'It is generally safe to delete newly created users (and test users and '.
      'so on), but less safe to delete established users. If possible, '.
      'disable them instead.');

    $str4 = pht('To permanently destroy this user, run this command:');

    $form = id(new AphrontFormView())
      ->setUser($admin)
      ->appendRemarkupInstructions(
        pht(
          "  phabricator/ $ ./bin/remove destroy %s\n",
          csprintf('%R', '@'.$user->getUsername())));

    return $this->newDialog()
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle(pht('Permanently Delete User'))
      ->setShortTitle(pht('Delete User'))
      ->appendParagraph($str1)
      ->appendParagraph($str2)
      ->appendParagraph($str3)
      ->appendParagraph($str4)
      ->appendChild($form->buildLayoutView())
      ->addCancelButton($profile_uri, pht('Close'));
  }

  private function buildDeleteSelfResponse($profile_uri) {
    return $this->newDialog()
      ->setTitle(pht('You Shall Journey No Farther'))
      ->appendParagraph(
        pht(
          'As you stare into the gaping maw of the abyss, something '.
          'holds you back.'))
      ->appendParagraph(pht('You can not delete your own account.'))
      ->addCancelButton($profile_uri, pht('Turn Back'));
  }


}
