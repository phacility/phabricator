<?php

final class HarbormasterPlanOrderController extends HarbormasterController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $request->validateCSRF();

    $this->requireApplicationCapability(
      HarbormasterCapabilityManagePlans::CAPABILITY);

    $plan = id(new HarbormasterBuildPlanQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$plan) {
      return new Aphront404Response();
    }

    // Load all steps.
    $order = $request->getStrList('order');
    $steps = id(new HarbormasterBuildStepQuery())
      ->setViewer($user)
      ->withIDs($order)
      ->execute();
    $steps = array_select_keys($steps, $order);
    $reordered_steps = array();

    // Apply sequences.
    $sequence = 1;
    foreach ($steps as $step) {
      $step->setSequence($sequence++);
      $step->save();

      $reordered_steps[] = $step;
    }

    // NOTE: Reordering steps may invalidate artifacts. This is fine; the UI
    // will show that there are ordering issues.

    // Force the page to re-render.
    return id(new AphrontRedirectResponse());
  }

}
