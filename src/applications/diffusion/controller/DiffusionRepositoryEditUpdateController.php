<?php

final class DiffusionRepositoryEditUpdateController
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

    if ($request->isFormPost()) {
      $params = array(
        'repositories' => array(
          $repository->getPHID(),
        ),
      );

      id(new ConduitCall('diffusion.looksoon', $params))
        ->setUser($viewer)
        ->execute();

      return id(new AphrontRedirectResponse())->setURI($panel_uri);
    }

    $doc_name = 'Diffusion User Guide: Repository Updates';
    $doc_href = PhabricatorEnv::getDoclink($doc_name);
    $doc_link = phutil_tag(
      'a',
      array(
        'href' => $doc_href,
        'target' => '_blank',
      ),
      $doc_name);

    return $this->newDialog()
      ->setTitle(pht('Update Repository Now'))
      ->appendParagraph(
        pht(
          'Normally, Phabricator automatically updates repositories '.
          'based on how much time has elapsed since the last commit. '.
          'This helps reduce load if you have a large number of mostly '.
          'inactive repositories, which is common.'))
      ->appendParagraph(
        pht(
          'You can manually schedule an update for this repository. The '.
          'daemons will perform the update as soon as possible. This may '.
          'be helpful if you have just made a commit to a rarely used '.
          'repository.'))
      ->appendParagraph(
        pht(
          'To learn more about how Phabricator updates repositories, '.
          'read %s in the documentation.',
          $doc_link))
      ->addCancelButton($panel_uri)
      ->addSubmitButton(pht('Schedule Update'));
  }


}
