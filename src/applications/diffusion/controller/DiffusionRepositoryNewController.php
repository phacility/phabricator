<?php

final class DiffusionRepositoryNewController
  extends DiffusionController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $this->requireApplicationCapability(
      DiffusionCapabilityCreateRepositories::CAPABILITY);

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
                'Create a new, empty repository which Phabricator will host.'),
              phutil_tag('br'),
              pht(
                '%s: This feature is very new and barely works. Use it '.
                'at your own risk! By choosing this option, you accept great '.
                'mortal peril.',
                phutil_tag('strong', array(), pht('BEWARE'))),
            ))
          ->addButton(
            'import',
            pht('Import an Existing External Repository'),
            pht(
              'Import a repository hosted somewhere else, like GitHub, '.
              'Bitbucket, or your organization\'s existing servers. '.
              'Phabricator will read changes from the repository but will '.
              'not host or manage it. The authoritative master version of '.
              'the repository will stay where it is now.')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Continue'))
          ->addCancelButton($this->getApplicationURI()));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('New Repository')));

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
        'device' => true,
      ));
  }

}
