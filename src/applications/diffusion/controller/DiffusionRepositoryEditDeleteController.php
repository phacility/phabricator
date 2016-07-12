<?php

final class DiffusionRepositoryEditDeleteController
  extends DiffusionRepositoryManageController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContextForEdit();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $panel_uri = id(new DiffusionRepositoryBasicsManagementPanel())
      ->setRepository($repository)
      ->getPanelURI();

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

    return $this->newDialog()
      ->setTitle(pht('Really want to delete the repository?'))
      ->appendChild($body)
      ->addCancelButton($panel_uri, pht('Okay'));
  }

}
