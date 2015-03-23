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
      $result = array();
      foreach ($application->getCapabilities() as $capability) {
        $old = $application->getPolicy($capability);
        $new = $request->getStr('policy:'.$capability);

        if ($old == $new) {
          // No change to the setting.
          continue;
        }

        if (empty($policies[$new])) {
          // Not a standard policy, check for a custom policy.
          $policy = id(new PhabricatorPolicyQuery())
            ->setViewer($user)
            ->withPHIDs(array($new))
            ->executeOne();
          if (!$policy) {
            // Not a custom policy either. Can't set the policy to something
            // invalid, so skip this.
            continue;
          }
        }

        if ($new == PhabricatorPolicies::POLICY_PUBLIC) {
          $capobj = PhabricatorPolicyCapability::getCapabilityByKey(
            $capability);
          if (!$capobj || !$capobj->shouldAllowPublicPolicySetting()) {
            // Can't set non-public policies to public.
            continue;
          }
        }

        $result[$capability] = $new;
      }

      if ($result) {
        $key = 'phabricator.application-settings';
        $config_entry = PhabricatorConfigEntry::loadConfigEntry($key);
        $value = $config_entry->getValue();

        $phid = $application->getPHID();
        if (empty($value[$phid])) {
          $value[$application->getPHID()] = array();
        }
        if (empty($value[$phid]['policy'])) {
          $value[$phid]['policy'] = array();
        }

        $value[$phid]['policy'] = $result + $value[$phid]['policy'];

        // Don't allow users to make policy edits which would lock them out of
        // applications, since they would be unable to undo those actions.
        PhabricatorEnv::overrideConfig($key, $value);
        PhabricatorPolicyFilter::mustRetainCapability(
          $user,
          $application,
          PhabricatorPolicyCapability::CAN_VIEW);

        PhabricatorPolicyFilter::mustRetainCapability(
          $user,
          $application,
          PhabricatorPolicyCapability::CAN_EDIT);

        PhabricatorConfigEditor::storeNewValue(
          $user,
          $config_entry,
          $value,
          PhabricatorContentSource::newFromRequest($request));
      }

      return id(new AphrontRedirectResponse())->setURI($view_uri);
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
        $form->appendChild(
          id(new AphrontFormPolicyControl())
          ->setUser($user)
          ->setDisabled($locked)
          ->setCapability($capability)
          ->setPolicyObject($application)
          ->setPolicies($policies)
          ->setLabel($label)
          ->setName('policy:'.$capability)
          ->setCaption($caption));
      }

    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save Policies'))
        ->addCancelButton($view_uri));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($application->getName(), $view_uri);
    $crumbs->addTextCrumb(pht('Edit Policies'));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Edit Policies: %s', $application->getName()));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $title,
      ));
  }

}
