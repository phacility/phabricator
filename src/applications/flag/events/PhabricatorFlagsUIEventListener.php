<?php

final class PhabricatorFlagsUIEventListener extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS:
        $this->handleActionEvent($event);
      break;
    }
  }

  private function handleActionEvent($event) {
    $user = $event->getUser();
    $object = $event->getValue('object');

    if (!$object || !$object->getPHID()) {
      // If we have no object, or the object doesn't have a PHID yet, we can't
      // flag it.
      return;
    }

    if (!($object instanceof PhabricatorFlaggableInterface)) {
      return;
    }

    if (!$this->canUseApplication($event->getUser())) {
      return null;
    }

    $flag = PhabricatorFlagQuery::loadUserFlag($user, $object->getPHID());

    if ($flag) {
      $color = PhabricatorFlagColor::getColorName($flag->getColor());
      $flag_icon = PhabricatorFlagColor::getIcon($flag->getColor());
      $flag_action = id(new PhabricatorActionView())
        ->setWorkflow(true)
        ->setHref('/flag/delete/'.$flag->getID().'/')
        ->setName(pht('Remove %s Flag', $color))
        ->setIcon($flag_icon);
    } else {
      $flag_action = id(new PhabricatorActionView())
        ->setWorkflow(true)
        ->setHref('/flag/edit/'.$object->getPHID().'/')
        ->setName(pht('Flag For Later'))
        ->setIcon('fa-flag');

      if (!$user->isLoggedIn()) {
        $flag_action->setDisabled(true);
      }
    }

    $actions = $event->getValue('actions');
    $actions[] = $flag_action;
    $event->setValue('actions', $actions);
  }

}
