<?php

abstract class PhabricatorEventListener extends PhutilEventListener {

  private $application;

  public function setApplication(PhabricatorApplication $application) {
    $this->application = $application;
    return $this;
  }

  public function getApplication() {
    return $this->application;
  }

  public function hasApplicationCapability(
    PhabricatorUser $viewer,
    $capability) {
    return PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $this->getApplication(),
      $capability);
  }

  public function canUseApplication(PhabricatorUser $viewer) {
    return $this->hasApplicationCapability(
      $viewer,
      PhabricatorPolicyCapability::CAN_VIEW);
  }

  protected function addActionMenuItems(PhutilEvent $event, $items) {
    if ($event->getType() !== PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS) {
      throw new Exception(pht('Not an action menu event!'));
    }

    if (!$items) {
      return;
    }

    if (!is_array($items)) {
      $items = array($items);
    }

    $event_actions = $event->getValue('actions');
    foreach ($items as $item) {
      $event_actions[] = $item;
    }
    $event->setValue('actions', $event_actions);
  }

}
