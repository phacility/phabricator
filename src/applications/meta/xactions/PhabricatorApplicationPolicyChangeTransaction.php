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

  public function applyInternalEffects($object, $value) {
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
    PhabricatorConfigEditor::storeNewValue(
      $user,
      $config_entry,
      $current_value,
      $content_source);
  }

  public function getTitle() {
    $old = $this->renderPolicy($this->getOldValue());
    $new = $this->renderPolicy($this->getNewValue());

    return pht(
      '%s changed the "%s" policy from "%s" to "%s".',
      $this->renderAuthor(),
      $this->renderCapability(),
      $old,
      $new);
  }

  public function getTitleForFeed() {
    $old = $this->renderPolicy($this->getOldValue());
    $new = $this->renderPolicy($this->getNewValue());

    return pht(
      '%s changed the "%s" policy for application %s from "%s" to "%s".',
      $this->renderAuthor(),
      $this->renderCapability(),
      $this->renderObject(),
      $old,
      $new);
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

  private function renderPolicy($name) {
    $policies = $this->getAllPolicies();
    if (empty($policies[$name])) {
      // Not a standard policy, check for a custom policy.
      $policy = id(new PhabricatorPolicyQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs(array($name))
        ->executeOne();
      $policies[$name] = $policy;
    }

    $policy = idx($policies, $name);
    return $this->renderValue($policy->getFullName());
  }

  private function getAllPolicies() {
    if (!$this->policies) {
      $viewer = $this->getViewer();
      $application = $this->getObject();
      $this->policies = id(new PhabricatorPolicyQuery())
        ->setViewer($viewer)
        ->setObject($application)
        ->execute();
    }

    return $this->policies;
  }

  private function renderCapability() {
    $application = $this->getObject();
    $capability = $this->getCapabilityName();
    return $application->getCapabilityLabel($capability);
  }

  private function getCapabilityName() {
    return $this->getMetadataValue(self::METADATA_ATTRIBUTE);
  }

}
