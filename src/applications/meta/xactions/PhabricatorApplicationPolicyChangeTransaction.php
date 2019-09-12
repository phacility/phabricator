<?php

final class PhabricatorApplicationPolicyChangeTransaction
  extends PhabricatorApplicationTransactionType {

  const TRANSACTIONTYPE = 'application.policy';
  const METADATA_ATTRIBUTE = 'capability.name';

  private $policies;

  public function generateOldValue($object) {
    $application = $object;
    $capability = $this->getCapabilityName();
    return $application->getPolicy($capability);
  }

  public function applyExternalEffects($object, $value) {
    $application = $object;
    $user = $this->getActor();

    $key = 'phabricator.application-settings';
    $config_entry = PhabricatorConfigEntry::loadConfigEntry($key);
    $current_value = $config_entry->getValue();

    $phid = $application->getPHID();
    if (empty($current_value[$phid])) {
      $current_value[$application->getPHID()] = array();
    }
    if (empty($current_value[$phid]['policy'])) {
      $current_value[$phid]['policy'] = array();
    }

    $new = array($this->getCapabilityName() => $value);
    $current_value[$phid]['policy'] = $new + $current_value[$phid]['policy'];

    $editor = $this->getEditor();
    $content_source = $editor->getContentSource();

    // NOTE: We allow applications to have custom edit policies, but they are
    // currently stored in the Config application. The ability to edit Config
    // values is always restricted to administrators, today. Empower this
    // particular edit to punch through possible stricter policies, so normal
    // users can change application configuration if the application allows
    // them to do so.

    PhabricatorConfigEditor::storeNewValue(
      PhabricatorUser::getOmnipotentUser(),
      $config_entry,
      $current_value,
      $content_source,
      $user->getPHID());
  }

  public function getTitle() {
    return pht(
      '%s changed the %s policy from %s to %s.',
      $this->renderAuthor(),
      $this->renderCapability(),
      $this->renderOldPolicy(),
      $this->renderNewPolicy());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed the %s policy for application %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderCapability(),
      $this->renderObject(),
      $this->renderOldPolicy(),
      $this->renderNewPolicy());
  }

  public function validateTransactions($object, array $xactions) {
    $user = $this->getActor();
    $application = $object;
    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($application)
      ->execute();

    $errors = array();
    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();
      $capability = $xaction->getMetadataValue(self::METADATA_ATTRIBUTE);

      if (empty($policies[$new])) {
        // Not a standard policy, check for a custom policy.
        $policy = id(new PhabricatorPolicyQuery())
          ->setViewer($user)
          ->withPHIDs(array($new))
          ->executeOne();
        if (!$policy) {
          $errors[] = $this->newInvalidError(
            pht('Policy does not exist.'));
          continue;
        }
      } else {
        $policy = idx($policies, $new);
      }

      if (!$policy->isValidPolicyForEdit()) {
        $errors[] = $this->newInvalidError(
            pht('Can\'t set the policy to a policy you can\'t view!'));
          continue;
      }

      if ($new == PhabricatorPolicies::POLICY_PUBLIC) {
        $capobj = PhabricatorPolicyCapability::getCapabilityByKey(
          $capability);
        if (!$capobj || !$capobj->shouldAllowPublicPolicySetting()) {
          $errors[] = $this->newInvalidError(
            pht('Can\'t set non-public policies to public.'));
          continue;
        }
      }

      if (!$application->isCapabilityEditable($capability)) {
        $errors[] = $this->newInvalidError(
          pht('Capability "%s" is not editable for this application.',
            $capability));
        continue;
      }
    }

    // If we're changing these policies, the viewer needs to still be able to
    // view or edit the application under the new policy.
    $validate_map = array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
    $validate_map = array_fill_keys($validate_map, array());

    foreach ($xactions as $xaction) {
      $capability = $xaction->getMetadataValue(self::METADATA_ATTRIBUTE);
      if (!isset($validate_map[$capability])) {
        continue;
      }

      $validate_map[$capability][] = $xaction;
    }

    foreach ($validate_map as $capability => $cap_xactions) {
      if (!$cap_xactions) {
        continue;
      }

      $editor = $this->getEditor();
      $policy_errors = $editor->validatePolicyTransaction(
        $object,
        $cap_xactions,
        self::TRANSACTIONTYPE,
        $capability);

      foreach ($policy_errors as $error) {
        $errors[] = $error;
      }
    }

    return $errors;
  }

  private function renderCapability() {
    $application = $this->getObject();
    $capability = $this->getCapabilityName();
    $label = $application->getCapabilityLabel($capability);
    return $this->renderValue($label);
  }

  private function getCapabilityName() {
    return $this->getMetadataValue(self::METADATA_ATTRIBUTE);
  }

}
