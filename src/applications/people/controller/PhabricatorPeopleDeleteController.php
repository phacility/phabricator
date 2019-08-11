<?php

final class PhabricatorPeopleDeleteController
  extends PhabricatorPeopleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();
    $id = $request->getURIData('id');

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $manage_uri = $this->getApplicationURI("manage/{$id}/");

    $doc_uri = PhabricatorEnv::getDoclink(
      'Permanently Destroying Data');

    return $this->newDialog()
      ->setTitle(pht('Delete User'))
      ->appendParagraph(
        pht(
          'To permanently destroy this user, run this command from the '.
          'command line:'))
      ->appendCommand(
        csprintf(
          'phabricator/ $ ./bin/remove destroy %R',
          $user->getMonogram()))
      ->appendParagraph(
        pht(
          'Unless you have a very good reason to delete this user, consider '.
          'disabling them instead.'))
      ->appendParagraph(
        pht(
          'Users can not be permanently destroyed from the web interface. '.
          'See %s in the documentation for more information.',
          phutil_tag(
            'a',
            array(
              'href' => $doc_uri,
              'target' => '_blank',
            ),
            pht('Permanently Destroying Data'))))
      ->addCancelButton($manage_uri, pht('Close'));
  }

}
