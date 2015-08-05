<?php

final class HarbormasterStepAddController extends HarbormasterController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $this->requireApplicationCapability(
      HarbormasterManagePlansCapability::CAPABILITY);

    $plan = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$plan) {
      return new Aphront404Response();
    }

    $plan_id = $plan->getID();
    $cancel_uri = $this->getApplicationURI("plan/{$plan_id}/");

    $all = HarbormasterBuildStepImplementation::getImplementations();
    foreach ($all as $key => $impl) {
      if ($impl->shouldRequireAutotargeting()) {
        unset($all[$key]);
      }
    }

    $errors = array();
    if ($request->isFormPost()) {
      $class = $request->getStr('class');
      if (empty($all[$class])) {
        $errors[] = pht('Choose the type of build step you want to add.');
      }
      if (!$errors) {
        $new_uri = $this->getApplicationURI("step/new/{$plan_id}/{$class}/");
        return id(new AphrontRedirectResponse())->setURI($new_uri);
      }
    }

    $control = id(new AphrontFormRadioButtonControl())
      ->setName('class');

    foreach ($all as $class => $implementation) {
      $control->addButton(
        $class,
        $implementation->getName(),
        $implementation->getGenericDescription());
    }

    if ($errors) {
      $errors = id(new PHUIInfoView())
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
