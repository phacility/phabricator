<?php

final class PhabricatorCalendarEventPolicyCodex
  extends PhabricatorPolicyCodex {

  public function getPolicyShortName() {
    $object = $this->getObject();

    if (!$object->isImportedEvent()) {
      return null;
    }

    return pht('Uses Import Policy');
  }

  public function getPolicyIcon() {
    $object = $this->getObject();

    if (!$object->isImportedEvent()) {
      return null;
    }

    return 'fa-download';
  }

  public function getPolicyTagClasses() {
    $object = $this->getObject();

    if (!$object->isImportedEvent()) {
      return array();
    }

    return array(
      'policy-adjusted-special',
    );
  }

  public function getPolicySpecialRuleDescriptions() {
    $object = $this->getObject();

    $rules = array();
    $rules[] = $this->newRule()
      ->setDescription(
        pht('The host of an event can always view and edit it.'));

    $rules[] = $this->newRule()
      ->setCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->setDescription(
        pht('Users who are invited to an event can always view it.'));


    $rules[] = $this->newRule()
      ->setCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->setIsActive($object->isImportedEvent())
      ->setDescription(
        pht(
          'Imported events can only be viewed by users who can view '.
          'the import source.'));

    $rules[] = $this->newRule()
      ->setCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->setIsActive($object->isImportedEvent())
      ->setDescription(
        pht(
          'Imported events can not be edited.'));

    return $rules;
  }


}
