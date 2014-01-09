<?php

final class DrydockBlueprintCreateController
  extends DrydockBlueprintController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $implementations =
      DrydockBlueprintImplementation::getAllBlueprintImplementations();

    $errors = array();
    $e_blueprint = null;

    if ($request->isFormPost()) {
      $class = $request->getStr('blueprint-type');
      if (!isset($implementations[$class])) {
        $e_blueprint = pht('Required');
        $errors[] = pht('You must choose a blueprint type.');
      }

      if (!$errors) {
        $edit_uri = $this->getApplicationURI('blueprint/edit/?class='.$class);
        return id(new AphrontRedirectResponse())->setURI($edit_uri);
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setErrors($errors);
    }

    $control = id(new AphrontFormRadioButtonControl())
      ->setName('blueprint-type')
      ->setLabel(pht('Blueprint Type'))
      ->setError($e_blueprint);

    foreach ($implementations as $implementation_name => $implementation) {
      $disabled = !$implementation->isEnabled();

      $control->addButton(
        $implementation_name,
        $implementation->getBlueprintName(),
        array(
          pht('Provides: %s', $implementation->getType()),
          phutil_tag('br'),
          phutil_tag('br'),
          $implementation->getDescription(),
        ),
        $disabled ? 'disabled' : null,
        $disabled);
    }

    $title = pht('Create New Blueprint');
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('New Blueprint'));

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($control)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($this->getApplicationURI('blueprint/'))
          ->setValue(pht('Continue')));

    $box = id(new PHUIObjectBoxView())
      ->setFormError($error_view)
      ->setHeaderText($title)
      ->setForm($form);

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
