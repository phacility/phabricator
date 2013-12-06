<?php

final class HarbormasterStepAddController
  extends HarbormasterController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $this->requireApplicationCapability(
      HarbormasterCapabilityManagePlans::CAPABILITY);

    $id = $this->id;

    $plan = id(new HarbormasterBuildPlanQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->executeOne();
    if ($plan === null) {
      throw new Exception("Build plan not found!");
    }

    $implementations = BuildStepImplementation::getImplementations();

    $cancel_uri = $this->getApplicationURI('plan/'.$plan->getID().'/');

    if ($request->isDialogFormPost()) {
      $class = $request->getStr('step-type');
      if (!in_array($class, $implementations)) {
        return $this->createDialog($implementations);
      }

      $steps = $plan->loadOrderedBuildSteps();

      $step = new HarbormasterBuildStep();
      $step->setBuildPlanPHID($plan->getPHID());
      $step->setClassName($class);
      $step->setDetails(array());
      $step->setSequence(count($steps) + 1);
      $step->save();

      $edit_uri = $this->getApplicationURI("step/edit/".$step->getID()."/");

      return id(new AphrontRedirectResponse())->setURI($edit_uri);
    }

    return $this->createDialog($implementations, $cancel_uri);
  }

  function createDialog(array $implementations, $cancel_uri) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $control = id(new AphrontFormRadioButtonControl())
      ->setName('step-type');

    foreach ($implementations as $implementation_name) {
      $implementation = new $implementation_name();
      $control
        ->addButton(
          $implementation_name,
          $implementation->getName(),
          $implementation->getGenericDescription());
    }

    $dialog = new AphrontDialogView();
    $dialog->setTitle(pht('Add New Step'))
            ->setUser($viewer)
            ->addSubmitButton(pht('Add Build Step'))
            ->addCancelButton($cancel_uri);
    $dialog->appendChild(
      phutil_tag(
        'p',
        array(),
        pht(
          'Select what type of build step you want to add: ')));
    $dialog->appendChild($control);
    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
