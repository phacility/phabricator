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
    if (!$plan) {
      return new Aphront404Response();
    }

    $cancel_uri = $this->getApplicationURI('plan/'.$plan->getID().'/');

    if ($request->isDialogFormPost()) {
      $class = $request->getStr('step-type');
      if (!HarbormasterBuildStepImplementation::getImplementation($class)) {
        return $this->createDialog($cancel_uri);
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

    return $this->createDialog($cancel_uri);
  }

  private function createDialog($cancel_uri) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $control = id(new AphrontFormRadioButtonControl())
      ->setName('step-type');

    $all = HarbormasterBuildStepImplementation::getImplementations();
    foreach ($all as $class => $implementation) {
      $control->addButton(
        $class,
        $implementation->getName(),
        $implementation->getGenericDescription());
    }

    return $this->newDialog()
      ->setTitle(pht('Add New Step'))
      ->addSubmitButton(pht('Add Build Step'))
      ->addCancelButton($cancel_uri)
      ->appendParagraph(pht('Choose a type of build step to add:'))
      ->appendChild($control);
  }

}
