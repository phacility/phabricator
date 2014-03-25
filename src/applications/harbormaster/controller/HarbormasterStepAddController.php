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

    $plan = id(new HarbormasterBuildPlanQuery())
        ->setViewer($viewer)
        ->withIDs(array($this->id))
        ->executeOne();
    if (!$plan) {
      return new Aphront404Response();
    }

    $plan_id = $plan->getID();
    $cancel_uri = $this->getApplicationURI("plan/{$plan_id}/");

    $errors = array();
    if ($request->isFormPost()) {
      $class = $request->getStr('class');
      if (!HarbormasterBuildStepImplementation::getImplementation($class)) {
        $errors[] = pht(
          'Choose the type of build step you want to add.');
      }
      if (!$errors) {
        $new_uri = $this->getApplicationURI("step/new/{$plan_id}/{$class}/");
        return id(new AphrontRedirectResponse())->setURI($new_uri);
      }
    }

    $control = id(new AphrontFormRadioButtonControl())
      ->setName('class');

    $all = HarbormasterBuildStepImplementation::getImplementations();
    foreach ($all as $class => $implementation) {
      $control->addButton(
        $class,
        $implementation->getName(),
        $implementation->getGenericDescription());
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())
        ->setErrors($errors);
    }

    return $this->newDialog()
      ->setTitle(pht('Add New Step'))
      ->addSubmitButton(pht('Add Build Step'))
      ->addCancelButton($cancel_uri)
      ->appendChild($errors)
      ->appendParagraph(pht('Choose a type of build step to add:'))
      ->appendChild($control);
  }

}
