<?php

final class HarbormasterBuildableEditController
  extends HarbormasterController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $this->requireApplicationCapability(
      HarbormasterCapabilityManagePlans::CAPABILITY);

    if ($this->id) {
      $buildable = id(new HarbormasterBuildableQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
        ->executeOne();
      if (!$buildable) {
        return new Aphront404Response();
      }
    } else {
      $buildable = HarbormasterBuildable::initializeNewBuildable($viewer);
    }

    $e_name = true;
    $v_name = null;

    $errors = array();
    if ($request->isFormPost()) {
      $v_name = $request->getStr('buildablePHID');

      if ($v_name) {
        $object = id(new PhabricatorObjectQuery())
          ->setViewer($viewer)
          ->withNames(array($v_name))
          ->executeOne();

        if ($object instanceof DifferentialRevision) {
          $revision = $object;
          $object = $object->loadActiveDiff();
          $buildable
            ->setBuildablePHID($object->getPHID())
            ->setContainerPHID($revision->getPHID());
        } else if ($object instanceof PhabricatorRepositoryCommit) {
          $buildable
            ->setBuildablePHID($object->getPHID())
            ->setContainerPHID($object->getRepository()->getPHID());
        } else {
          $e_name = pht('Invalid');
          $errors[] = pht('Enter the name of a revision or commit.');
        }
      } else {
        $e_name = pht('Required');
        $errors[] = pht('You must choose a revision or commit to build.');
      }

      if (!$errors) {
        $buildable->save();

        $buildable_uri = '/B'.$buildable->getID();
        return id(new AphrontRedirectResponse())->setURI($buildable_uri);
      }
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())->setErrors($errors);
    }

    $is_new = (!$buildable->getID());
    if ($is_new) {
      $title = pht('New Buildable');
      $cancel_uri = $this->getApplicationURI();
      $save_button = pht('Create Buildable');
    } else {
      $id = $buildable->getID();

      $title = pht('Edit Buildable');
      $cancel_uri = "/B{$id}";
      $save_button = pht('Save Buildable');
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    if ($is_new) {
      $form
        ->appendRemarkupInstructions(
          pht(
            'Enter the name of a commit or revision, like `rX123456789` '.
            'or `D123`.'))
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setLabel('Buildable Name')
            ->setName('buildablePHID')
            ->setError($e_name)
            ->setValue($v_name));
    } else {
      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Buildable'))
          ->setValue($buildable->getBuildableHandle()->renderLink()));
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue($save_button)
        ->addCancelButton($cancel_uri));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormError($errors)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    if ($is_new) {
      $crumbs->addTextCrumb(pht('New Buildable'));
    } else {
      $id = $buildable->getID();
      $crumbs->addTextCrumb("B{$id}", "/B{$id}");
      $crumbs->addTextCrumb(pht('Edit'));
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
