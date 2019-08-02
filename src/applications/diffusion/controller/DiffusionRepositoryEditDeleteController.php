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

    $doc_uri = PhabricatorEnv::getDoclink(
      'Permanently Destroying Data');

    return $this->newDialog()
      ->setTitle(pht('Delete Repository'))
      ->appendParagraph(
        pht(
          'To permanently destroy this repository, run this command from '.
          'the command line:'))
      ->appendCommand(
        csprintf(
          'phabricator/ $ ./bin/remove destroy %R',
          $repository->getMonogram()))
      ->appendParagraph(
        pht(
          'Repositories can not be permanently destroyed from the web '.
          'interface. See %s in the documentation for more information.',
          phutil_tag(
            'a',
            array(
              'href' => $doc_uri,
              'target' => '_blank',
            ),
            pht('Permanently Destroying Data'))))
      ->addCancelButton($panel_uri, pht('Close'));
  }

}
