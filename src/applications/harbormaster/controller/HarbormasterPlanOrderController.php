<?php

/**
 * @group search
 */
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

    // We must ensure that steps with artifacts become invalid if they are
    // placed before the steps that produce them.
    foreach ($reordered_steps as $step) {
      $implementation = $step->getStepImplementation();
      $settings = $implementation->getSettings();
      foreach ($implementation->getSettingDefinitions() as $name => $opt) {
        switch ($opt['type']) {
          case BuildStepImplementation::SETTING_TYPE_ARTIFACT:
            $value = $settings[$name];
            $filter = $opt['artifact_type'];
            $available_artifacts =
              BuildStepImplementation::getAvailableArtifacts(
                $plan,
                $reordered_steps,
                $step,
                $filter);
            $artifact_found = false;
            foreach ($available_artifacts as $key => $type) {
              if ($key === $value) {
                $artifact_found = true;
              }
            }
            if (!$artifact_found) {
              $step->setDetail($name, null);
            }
            break;
        }
        $step->save();
      }
    }

    // Force the page to re-render.
    return id(new AphrontRedirectResponse());
  }

}
