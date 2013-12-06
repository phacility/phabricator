<?php

final class HarbormasterStepEditController
  extends HarbormasterController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $this->requireApplicationCapability(
      HarbormasterCapabilityManagePlans::CAPABILITY);

    $step = id(new HarbormasterBuildStepQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$step) {
      return new Aphront404Response();
    }

    $plan = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($step->getBuildPlanPHID()))
      ->executeOne();
    if (!$plan) {
      return new Aphront404Response();
    }

    $implementation = $step->getStepImplementation();
    $implementation->validateSettingDefinitions();
    $settings = $implementation->getSettings();

    $errors = array();
    if ($request->isFormPost()) {
      foreach ($implementation->getSettingDefinitions() as $name => $opt) {
        $readable_name = $this->getReadableName($name, $opt);
        $value = $this->getValueFromRequest($request, $name, $opt['type']);

        // TODO: This won't catch any validation issues unless the field
        // is missing completely.  How should we check if the user is
        // required to enter an integer?
        if ($value === null) {
          $errors[] = $readable_name.' is not valid.';
        } else {
          $step->setDetail($name, $value);
        }
      }

      if (count($errors) === 0) {
        $step->save();

        return id(new AphrontRedirectResponse())
          ->setURI($this->getApplicationURI('plan/'.$plan->getID().'/'));
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $instructions = $implementation->getSettingRemarkupInstructions();
    if ($instructions !== null) {
      $form->appendRemarkupInstructions($instructions);
    }

    // We need to render out all of the fields for the settings that
    // the implementation has.
    foreach ($implementation->getSettingDefinitions() as $name => $opt) {
      if ($request->isFormPost()) {
        $value = $this->getValueFromRequest($request, $name, $opt['type']);
      } else {
        $value = $settings[$name];
      }

      switch ($opt['type']) {
        case BuildStepImplementation::SETTING_TYPE_STRING:
        case BuildStepImplementation::SETTING_TYPE_INTEGER:
          $control = id(new AphrontFormTextControl())
            ->setLabel($this->getReadableName($name, $opt))
            ->setName($name)
            ->setValue($value);
          break;
        case BuildStepImplementation::SETTING_TYPE_BOOLEAN:
          $control = id(new AphrontFormCheckboxControl())
            ->setLabel($this->getReadableName($name, $opt))
            ->setName($name)
            ->setValue($value);
          break;
        case BuildStepImplementation::SETTING_TYPE_ARTIFACT:
          $filter = $opt['artifact_type'];
          $available_artifacts =
            BuildStepImplementation::loadAvailableArtifacts(
              $plan,
              $step,
              $filter);
          $options = array();
          foreach ($available_artifacts as $key => $type) {
            $options[$key] = $key;
          }
          $control = id(new AphrontFormSelectControl())
            ->setLabel($this->getReadableName($name, $opt))
            ->setName($name)
            ->setValue($value)
            ->setOptions($options);
          break;
        default:
          throw new Exception("Unable to render field with unknown type.");
      }

      if (isset($opt['description'])) {
        $control->setCaption($opt['description']);
      }

      $form->appendChild($control);
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save Step Configuration'))
        ->addCancelButton(
          $this->getApplicationURI('plan/'.$plan->getID().'/')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Edit Step: '.$implementation->getName())
      ->setValidationException(null)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $id = $plan->getID();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht("Plan %d", $id))
        ->setHref($this->getApplicationURI("plan/{$id}/")));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Edit Step')));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $implementation->getName(),
        'device' => true,
      ));
  }

  public function getReadableName($name, $opt) {
    $readable_name = $name;
    if (isset($opt['name'])) {
      $readable_name = $opt['name'];
    }
    return $readable_name;
  }

  public function getValueFromRequest(AphrontRequest $request, $name, $type) {
    switch ($type) {
      case BuildStepImplementation::SETTING_TYPE_STRING:
      case BuildStepImplementation::SETTING_TYPE_ARTIFACT:
        return $request->getStr($name);
        break;
      case BuildStepImplementation::SETTING_TYPE_INTEGER:
        return $request->getInt($name);
        break;
      case BuildStepImplementation::SETTING_TYPE_BOOLEAN:
        return $request->getBool($name);
        break;
      default:
        throw new Exception("Unsupported setting type '".$type."'.");
    }
  }

}
