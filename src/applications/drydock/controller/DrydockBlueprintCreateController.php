<?php

final class DrydockBlueprintCreateController
  extends DrydockBlueprintController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $this->requireApplicationCapability(
      DrydockCreateBlueprintsCapability::CAPABILITY);

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
      ->setFormErrors($errors)
      ->setHeaderText($title)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
      ));
  }

}
