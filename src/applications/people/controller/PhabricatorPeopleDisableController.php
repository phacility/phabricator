<?php

final class PhabricatorPeopleDisableController
  extends PhabricatorPeopleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');
    $via = $request->getURIData('id');

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

    $is_disapprove = ($via == 'disapprove');
    if ($is_disapprove) {
      $done_uri = $this->getApplicationURI('query/approval/');
      $should_disable = true;
    } else {
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
      id(new PhabricatorUserEditor())
        ->setActor($viewer)
        ->disableUser($user, $should_disable);

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    if ($should_disable) {
      $title = pht('Disable User?');
      $short_title = pht('Disable User');

      $body = pht(
        'Disable %s? They will no longer be able to access Phabricator or '.
        'receive email.',
        phutil_tag('strong', array(), $user->getUsername()));

      $submit = pht('Disable User');
    } else {
      $title = pht('Enable User?');
      $short_title = pht('Enable User');

      $body = pht(
        'Enable %s? They will be able to access Phabricator and receive '.
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
