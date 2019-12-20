<?php

final class ManiphestTaskPolicyCodex
  extends PhabricatorPolicyCodex {

  public function getPolicyShortName() {
    $object = $this->getObject();

    if ($object->areEditsLocked()) {
      return pht('Edits Locked');
    }

    return null;
  }

  public function getPolicyIcon() {
    $object = $this->getObject();

    if ($object->areEditsLocked()) {
      return 'fa-lock';
    }

    return null;
  }

  public function getPolicyTagClasses() {
    $object = $this->getObject();
    $classes = array();

    if ($object->areEditsLocked()) {
      $classes[] = 'policy-adjusted-locked';
    }

    return $classes;
  }

  public function getPolicySpecialRuleDescriptions() {
    $object = $this->getObject();

    $rules = array();

    $rules[] = $this->newRule()
      ->setCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->setDescription(
        pht('The owner of a task can always view and edit it.'));

    $rules[] = $this->newRule()
      ->setCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->setIsActive($object->areEditsLocked())
      ->setDescription(
        pht(
          'Tasks with edits locked may only be edited by their owner.'));

    return $rules;
  }

  public function getPolicyForEdit($capability) {

    // When a task has its edits locked, the effective edit policy is locked
    // to "No One". However, the task owner may still bypass the lock and edit
    // the task. When they do, we want the control in the UI to have the
    // correct value. Return the real value stored on the object.

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getObject()->getEditPolicy();
    }

    return parent::getPolicyForEdit($capability);
  }

}
