<?php

final class PhabricatorApplicationEditController
  extends PhabricatorApplicationsController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $application = $request->getURIData('application');

    $application = id(new PhabricatorApplicationQuery())
      ->setViewer($user)
      ->withClasses(array($application))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$application) {
      return new Aphront404Response();
    }

    $title = $application->getName();

    $view_uri = $this->getApplicationURI('view/'.get_class($application).'/');

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($application)
      ->execute();

    if ($request->isFormPost()) {
      $xactions = array();

      $result = array();
      $template = $application->getApplicationTransactionTemplate();
      foreach ($application->getCapabilities() as $capability) {
        if (!$application->isCapabilityEditable($capability)) {
          continue;
        }

        $old = $application->getPolicy($capability);
        $new = $request->getStr('policy:'.$capability);

        if ($old == $new) {
          // No change to the setting.
          continue;
        }

        $result[$capability] = $new;

        $xactions[] = id(clone $template)
          ->setTransactionType(
              PhabricatorApplicationPolicyChangeTransaction::TRANSACTIONTYPE)
          ->setMetadataValue(
            PhabricatorApplicationPolicyChangeTransaction::METADATA_ATTRIBUTE,
            $capability)
          ->setNewValue($new);
      }

      if ($result) {
        $editor = id(new PhabricatorApplicationEditor())
          ->setActor($user)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true)
          ->setContinueOnMissingFields(true);

        try {
          $editor->applyTransactions($application, $xactions);
          return id(new AphrontRedirectResponse())->setURI($view_uri);
        } catch (PhabricatorApplicationTransactionValidationException $ex) {
          $validation_exception = $ex;
        }

        return $this->newDialog()
          ->setTitle('Validation Failed')
          ->setValidationException($validation_exception)
          ->addCancelButton($view_uri);
      }
    }

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $user,
      $application);

    $form = id(new AphrontFormView())
      ->setUser($user);

    $locked_policies = PhabricatorEnv::getEnvConfig('policy.locked');
    foreach ($application->getCapabilities() as $capability) {
      $label = $application->getCapabilityLabel($capability);
      $can_edit = $application->isCapabilityEditable($capability);
      $locked = idx($locked_policies, $capability);
      $caption = $application->getCapabilityCaption($capability);

      if (!$can_edit || $locked) {
        $form->appendChild(
          id(new AphrontFormStaticControl())
            ->setLabel($label)
            ->setValue(idx($descriptions, $capability))
            ->setCaption($caption));
      } else {
        $control = id(new AphrontFormPolicyControl())
          ->setUser($user)
          ->setDisabled($locked)
          ->setCapability($capability)
          ->setPolicyObject($application)
          ->setPolicies($policies)
          ->setLabel($label)
          ->setName('policy:'.$capability)
          ->setCaption($caption);

        $template = $application->getCapabilityTemplatePHIDType($capability);
        if ($template) {
          $phid_types = PhabricatorPHIDType::getAllTypes();
          $phid_type = idx($phid_types, $template);
          if ($phid_type) {
            $template_object = $phid_type->newObject();
            if ($template_object) {
              $template_policies = id(new PhabricatorPolicyQuery())
                ->setViewer($user)
                ->setObject($template_object)
                ->execute();

              // NOTE: We want to expose both any object template policies
              // (like "Subscribers") and any custom policy.
              $all_policies = $template_policies + $policies;

              $control->setPolicies($all_policies);
              $control->setTemplateObject($template_object);
            }
          }

          $control->setTemplatePHIDType($template);
        }

        $form->appendControl($control);
      }

    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save Policies'))
        ->addCancelButton($view_uri));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($application->getName(), $view_uri);
    $crumbs->addTextCrumb(pht('Edit Policies'));
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Edit Policies: %s', $application->getName()))
      ->setHeaderIcon('fa-pencil');

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Policies'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $object_box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
