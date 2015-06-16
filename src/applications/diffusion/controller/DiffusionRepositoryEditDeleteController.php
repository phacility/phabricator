<?php

final class DiffusionRepositoryEditDeleteController
  extends DiffusionRepositoryEditController {

  protected function processDiffusionRequest(AphrontRequest $request) {
    $viewer = $request->getUser();
    $drequest = $this->diffusionRequest;
    $repository = $drequest->getRepository();

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($repository->getID()))
      ->executeOne();
    if (!$repository) {
      return new Aphront404Response();
    }

    $edit_uri = $this->getRepositoryControllerURI($repository, 'edit/');

    $dialog = new AphrontDialogView();
    $text_1 = pht(
      'If you really want to delete the repository, run this command from '.
      'the command line:');
    $command = csprintf(
      'phabricator/ $ ./bin/remove destroy %R',
      $repository->getMonogram());
    $text_2 = pht(
      'Repositories touch many objects and as such deletes are '.
      'prohibitively expensive to run from the web UI.');
    $body = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      array(
        phutil_tag('p', array(), $text_1),
        phutil_tag('p', array(),
          phutil_tag('tt', array(), $command)),
        phutil_tag('p', array(), $text_2),
      ));

    $dialog = id(new AphrontDialogView())
      ->setUser($request->getUser())
      ->setTitle(pht('Really want to delete the repository?'))
      ->appendChild($body)
      ->addCancelButton($edit_uri, pht('Okay'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }


}
