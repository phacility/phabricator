<?php

final class DiffusionRepositoryEditLocalController
  extends DiffusionRepositoryEditController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $drequest = $this->diffusionRequest;
    $repository = $drequest->getRepository();

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($user)
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

    $v_local = $repository->getHumanReadableDetail('local-path');
    $e_local = true;
    $errors = array();

    if ($request->isFormPost()) {
      $v_local = $request->getStr('local');

      if (!strlen($v_local)) {
        $e_local = pht('Required');
        $errors[] = pht('You must specify a local path.');
      }

      if (!$errors) {
        $xactions = array();
        $template = id(new PhabricatorRepositoryTransaction());

        $type_local = PhabricatorRepositoryTransaction::TYPE_LOCAL_PATH;

        $xactions[] = id(clone $template)
          ->setTransactionType($type_local)
          ->setNewValue($v_local);

        try {
          id(new PhabricatorRepositoryEditor())
            ->setContinueOnNoEffect(true)
            ->setContentSourceFromRequest($request)
            ->setActor($user)
            ->applyTransactions($repository, $xactions);

          return id(new AphrontRedirectResponse())->setURI($edit_uri);
        } catch (Exception $ex) {
          $errors[] = $ex->getMessage();
        }
      }
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Edit Local')));

    $title = pht('Edit %s', $repository->getName());

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Form Errors'))
        ->setErrors($errors);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendRemarkupInstructions(
        pht(
          'You can adjust the local path for this repository here. This is '.
          'an advanced setting and you usually should not change it.'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('local')
          ->setLabel(pht('Local Path'))
          ->setValue($v_local)
          ->setError($e_local))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Local'))
          ->addCancelButton($edit_uri));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form)
      ->setFormError($error_view);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
