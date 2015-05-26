<?php

final class DiffusionRepositoryNewController extends DiffusionController {

  protected function processDiffusionRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $this->requireApplicationCapability(
      DiffusionCreateRepositoriesCapability::CAPABILITY);

    if ($request->isFormPost()) {
      if ($request->getStr('type')) {
        switch ($request->getStr('type')) {
          case 'create':
            $uri = $this->getApplicationURI('create/');
            break;
          case 'import':
          default:
            $uri = $this->getApplicationURI('import/');
            break;
        }

        return id(new AphrontRedirectResponse())->setURI($uri);
      }
    }

    $doc_href = PhabricatorEnv::getDoclink(
      'Diffusion User Guide: Repository Hosting');

    $doc_link = phutil_tag(
      'a',
      array(
        'href' => $doc_href,
        'target' => '_blank',
      ),
      pht('Diffusion User Guide: Repository Hosting'));

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormRadioButtonControl())
          ->setName('type')
          ->addButton(
            'create',
            pht('Create a New Hosted Repository'),
            array(
              pht(
                'Create a new, empty repository which Phabricator will host. '.
                'For instructions on configuring repository hosting, see %s.',
                $doc_link),
            ))
          ->addButton(
            'import',
            pht('Import an Existing External Repository'),
            pht(
              "Import a repository hosted somewhere else, like GitHub, ".
              "Bitbucket, or your organization's existing servers. ".
              "Phabricator will read changes from the repository but will ".
              "not host or manage it. The authoritative master version of ".
              "the repository will stay where it is now.")))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Continue'))
          ->addCancelButton($this->getApplicationURI()));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('New Repository'));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Create or Import Repository'))
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => pht('New Repository'),
      ));
  }

}
