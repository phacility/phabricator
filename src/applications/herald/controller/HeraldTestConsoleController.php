<?php

final class HeraldTestConsoleController extends HeraldController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $request = $this->getRequest();

    $object_name = trim($request->getStr('object_name'));

    $e_name = true;
    $errors = array();
    if ($request->isFormPost()) {
      if (!$object_name) {
        $e_name = 'Required';
        $errors[] = 'An object name is required.';
      }

      if (!$errors) {
        $matches = null;
        $object = null;
        if (preg_match('/^D(\d+)$/', $object_name, $matches)) {
          $object = id(new DifferentialRevision())->load($matches[1]);
          if (!$object) {
            $e_name = 'Invalid';
            $errors[] = 'No Differential Revision with that ID exists.';
          }
        } else if (preg_match('/^r([A-Z]+)(\w+)$/', $object_name, $matches)) {
          $repo = id(new PhabricatorRepository())->loadOneWhere(
            'callsign = %s',
            $matches[1]);
          if (!$repo) {
            $e_name = 'Invalid';
            $errors[] = 'There is no repository with the callsign '.
                        $matches[1].'.';
          }
          $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
            'repositoryID = %d AND commitIdentifier = %s',
            $repo->getID(),
            $matches[2]);
          if (!$commit) {
            $e_name = 'Invalid';
            $errors[] = 'There is no commit with that identifier.';
          }
          $object = $commit;
        } else {
          $e_name = 'Invalid';
          $errors[] = 'This object name is not recognized.';
        }

        if (!$errors) {
          if ($object instanceof DifferentialRevision) {
            $adapter = new HeraldDifferentialRevisionAdapter(
              $object,
              $object->loadActiveDiff());
          } else if ($object instanceof PhabricatorRepositoryCommit) {
            $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
              'commitID = %d',
              $object->getID());
            $adapter = new HeraldCommitAdapter(
              $repo,
              $object,
              $data);
          } else {
            throw new Exception("Can not build adapter for object!");
          }

          $rules = HeraldRule::loadAllByContentTypeWithFullData(
            $adapter->getHeraldTypeName(),
            $object->getPHID());

          $engine = new HeraldEngine();
          $effects = $engine->applyRules($rules, $adapter);

          $dry_run = new HeraldDryRunAdapter();
          $engine->applyEffects($effects, $dry_run, $rules);

          $xscript = $engine->getTranscript();

          return id(new AphrontRedirectResponse())
            ->setURI('/herald/transcript/'.$xscript->getID().'/');
        }
      }
    }

    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Form Errors');
      $error_view->setErrors($errors);
    } else {
      $error_view = null;
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(hsprintf(
        '<p class="aphront-form-instructions">Enter an object to test rules '.
        'for, like a Diffusion commit (e.g., <tt>rX123</tt>) or a '.
        'Differential revision (e.g., <tt>D123</tt>). You will be shown the '.
        'results of a dry run on the object.</p>'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Object Name'))
          ->setName('object_name')
          ->setError($e_name)
          ->setValue($object_name))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Test Rules')));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Test Herald Rules'));
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);
    $panel->appendChild($form);
    $panel->setNoBackground();

    $nav = $this->renderNav();
    $nav->selectFilter('test');
    $nav->appendChild(
      array(
        $error_view,
        $panel,
      ));

    $crumbs = id($this->buildApplicationCrumbs())
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Transcripts'))
          ->setHref($this->getApplicationURI('/transcript/')))
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Test Console')));
    $nav->setCrumbs($crumbs);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Test Console',
      ));
  }

}
