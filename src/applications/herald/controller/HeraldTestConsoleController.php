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
        $e_name = pht('Required');
        $errors[] = pht('An object name is required.');
      }

      if (!$errors) {
        $object = id(new PhabricatorObjectQuery())
          ->setViewer($user)
          ->withNames(array($object_name))
          ->executeOne();

        if (!$object) {
          $e_name = pht('Invalid');
          $errors[] = pht('No object exists with that name.');
        }

        if (!$errors) {

          // TODO: Let the adapters claim objects instead.

          if ($object instanceof DifferentialRevision) {
            $adapter = HeraldDifferentialRevisionAdapter::newLegacyAdapter(
              $object,
              $object->loadActiveDiff());
          } else if ($object instanceof PhabricatorRepositoryCommit) {
            $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
              'commitID = %d',
              $object->getID());
            $adapter = HeraldCommitAdapter::newLegacyAdapter(
              $object->getRepository(),
              $object,
              $data);
          } else if ($object instanceof ManiphestTask) {
            $adapter = id(new HeraldManiphestTaskAdapter())
              ->setTask($object);
          } else if ($object instanceof PholioMock) {
            $adapter = id(new HeraldPholioMockAdapter())
              ->setMock($object);
          } else {
            throw new Exception("Can not build adapter for object!");
          }

          $rules = id(new HeraldRuleQuery())
            ->setViewer($user)
            ->withContentTypes(array($adapter->getAdapterContentType()))
            ->withDisabled(false)
            ->needConditionsAndActions(true)
            ->needAppliedToPHIDs(array($object->getPHID()))
            ->needValidateAuthors(true)
            ->execute();

          $engine = id(new HeraldEngine())
            ->setDryRun(true);

          $effects = $engine->applyRules($rules, $adapter);
          $engine->applyEffects($effects, $adapter, $rules);

          $xscript = $engine->getTranscript();

          return id(new AphrontRedirectResponse())
            ->setURI('/herald/transcript/'.$xscript->getID().'/');
        }
      }
    }

    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle(pht('Form Errors'));
      $error_view->setErrors($errors);
    } else {
      $error_view = null;
    }

    $text = pht(
      'Enter an object to test rules for, like a Diffusion commit (e.g., '.
      'rX123) or a Differential revision (e.g., D123). You will be shown '.
      'the results of a dry run on the object.');

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        phutil_tag('p', array('class' => 'aphront-form-instructions'), $text))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Object Name'))
          ->setName('object_name')
          ->setError($e_name)
          ->setValue($object_name))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Test Rules')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Herald Test Console'))
      ->setFormError($error_view)
      ->setForm($form);

    $crumbs = id($this->buildApplicationCrumbs())
      ->addTextCrumb(
        pht('Transcripts'),
        $this->getApplicationURI('/transcript/'))
      ->addTextCrumb(pht('Test Console'));

    return $this->buildApplicationPage(
      $box,
      array(
        'title' => pht('Test Console'),
        'device' => true,
      ));
  }

}
