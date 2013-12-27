<?php

final class DrydockBlueprintCreateController
  extends DrydockBlueprintController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $implementations =
      DrydockBlueprintImplementation::getAllBlueprintImplementations();

    if ($request->isFormPost()) {
      $class = $request->getStr('blueprint-type');
      if (!isset($implementations[$class])) {
        return $this->createDialog($implementations);
      }

      $blueprint = id(new DrydockBlueprint())
        ->setClassName($class)
        ->setDetails(array())
        ->setViewPolicy(PhabricatorPolicies::POLICY_ADMIN)
        ->setEditPolicy(PhabricatorPolicies::POLICY_ADMIN)
        ->save();

      $edit_uri = $this->getApplicationURI(
        "blueprint/edit/".$blueprint->getID()."/");

      return id(new AphrontRedirectResponse())->setURI($edit_uri);
    }

    return $this->createDialog($implementations);
  }

  function createDialog(array $implementations) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $control = id(new AphrontFormRadioButtonControl())
      ->setName('blueprint-type');

    foreach ($implementations as $implementation_name => $implementation) {
      $control
        ->addButton(
          $implementation_name,
          $implementation->getBlueprintClass(),
          $implementation->getDescription());
    }

    $dialog = new AphrontDialogView();
    $dialog->setTitle(pht('Create New Blueprint'))
            ->setUser($viewer)
            ->addSubmitButton(pht('Create Blueprint'))
            ->addCancelButton($this->getApplicationURI('blueprint/'));
    $dialog->appendChild(
      phutil_tag(
        'p',
        array(),
        pht(
          'Select what type of blueprint you want to create: ')));
    $dialog->appendChild($control);
    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
