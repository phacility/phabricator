<?php

final class PhabricatorRepositoryCreateController
  extends PhabricatorRepositoryController {

  public function processRequest() {


    $request = $this->getRequest();
    $user = $request->getUser();

    $e_name = true;
    $e_callsign = true;

    $repository = new PhabricatorRepository();

    $type_map = PhabricatorRepositoryType::getAllRepositoryTypes();
    $errors = array();

    if ($request->isFormPost()) {

      $repository->setName($request->getStr('name'));
      $repository->setCallsign($request->getStr('callsign'));
      $repository->setVersionControlSystem($request->getStr('type'));

      if (!strlen($repository->getName())) {
        $e_name = 'Required';
        $errors[] = 'Repository name is required.';
      } else {
        $e_name = null;
      }

      if (!strlen($repository->getCallsign())) {
        $e_callsign = 'Required';
        $errors[] = 'Callsign is required.';
      } else if (!preg_match('/^[A-Z]+$/', $repository->getCallsign())) {
        $e_callsign = 'Invalid';
        $errors[] = 'Callsign must be ALL UPPERCASE LETTERS.';
      } else {
        $e_callsign = null;
      }

      if (empty($type_map[$repository->getVersionControlSystem()])) {
        $errors[] = 'Invalid version control system.';
      }

      if (!$errors) {
        try {
          $repository->save();

          return id(new AphrontRedirectResponse())
            ->setURI('/repository/edit/'.$repository->getID().'/');

        } catch (AphrontQueryDuplicateKeyException $ex) {
          $e_callsign = 'Duplicate';
          $errors[] = 'Callsign must be unique. Another repository already '.
                      'uses that callsign.';
        }
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
      $error_view->setTitle('Form Errors');
    }


    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setAction('/repository/create/')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($repository->getName())
          ->setError($e_name)
          ->setCaption('Human-readable repository name.'))
      ->appendChild(
        '<p class="aphront-form-instructions">Select a "Callsign" &mdash; a '.
        'short, uppercase string to identify revisions in this repository. If '.
        'you choose "EX", revisions in this repository will be identified '.
        'with the prefix "rEX".</p>')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Callsign')
          ->setName('callsign')
          ->setValue($repository->getCallsign())
          ->setError($e_callsign)
          ->setCaption(
            'Short, UPPERCASE identifier. Once set, it can not be changed.'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Type')
          ->setName('type')
          ->setOptions($type_map)
          ->setValue($repository->getVersionControlSystem()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Create Repository')
          ->addCancelButton('/repository/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Create Repository');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Create Repository',
      ));
  }

}
