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
            $adapter = id(new HeraldCommitAdapter())
              ->setCommit($object);
          } else if ($object instanceof ManiphestTask) {
            $adapter = id(new HeraldManiphestTaskAdapter())
              ->setTask($object);
          } else if ($object instanceof PholioMock) {
            $adapter = id(new HeraldPholioMockAdapter())
              ->setMock($object);
          } else if ($object instanceof PhrictionDocument) {
            $adapter = id(new PhrictionDocumentHeraldAdapter())
              ->setDocument($object);
          } else {
            throw new Exception(pht('Can not build adapter for object!'));
          }

          $adapter->setIsNewObject(false);

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

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendRemarkupInstructions(
        pht(
        'Enter an object to test rules for, like a Diffusion commit (e.g., '.
        '`rX123`) or a Differential revision (e.g., `D123`). You will be '.
        'shown the results of a dry run on the object.'))
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
      ->setFormErrors($errors)
      ->setForm($form);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('test');
    $nav->appendChild($box);

    $crumbs = id($this->buildApplicationCrumbs())
      ->addTextCrumb(pht('Test Console'));
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Test Console'),
      ));
  }

}
