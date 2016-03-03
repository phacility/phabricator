<?php

final class DrydockBlueprintEditController extends DrydockBlueprintController {

  public function handleRequest(AphrontRequest $request) {
    $engine = id(new DrydockBlueprintEditEngine())
      ->setController($this);

    $id = $request->getURIData('id');
    if (!$id) {
      $this->requireApplicationCapability(
        DrydockCreateBlueprintsCapability::CAPABILITY);

      $type = $request->getStr('blueprintType');

      $impl = DrydockBlueprintImplementation::getNamedImplementation($type);
      if (!$impl || !$impl->isEnabled()) {
        return $this->buildTypeSelectionResponse();
      }

      $engine
        ->addContextParameter('blueprintType', $type)
        ->setBlueprintImplementation($impl);
    }

    return $engine->buildResponse();
  }

  private function buildTypeSelectionResponse() {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $implementations =
      DrydockBlueprintImplementation::getAllBlueprintImplementations();

    $errors = array();
    $e_blueprint = null;

    if ($request->isFormPost()) {
      $class = $request->getStr('blueprintType');
      if (!isset($implementations[$class])) {
        $e_blueprint = pht('Required');
        $errors[] = pht('You must choose a blueprint type.');
      }
    }

    $control = id(new AphrontFormRadioButtonControl())
      ->setName('blueprintType')
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

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($box);
  }

}
