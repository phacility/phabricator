<?php

final class PhabricatorFlagsUIEventListener extends PhutilEventListener {

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

    $flag = PhabricatorFlagQuery::loadUserFlag($user, $object->getPHID());

    if ($flag) {
      $color = PhabricatorFlagColor::getColorName($flag->getColor());
      $flag_action = id(new PhabricatorActionView())
        ->setWorkflow(true)
        ->setHref('/flag/delete/'.$flag->getID().'/')
        ->setName(phutil_escape_html('Remove '.$color.' Flag'))
        ->setIcon('flag-'.$flag->getColor());
    } else {
      $flag_action = id(new PhabricatorActionView())
        ->setWorkflow(true)
        ->setHref('/flag/edit/'.$object->getPHID().'/')
        ->setName('Flag For Later')
        ->setIcon('flag-ghost');

      if (!$user->isLoggedIn()) {
        $flag_action->setDisabled(true);
      }
    }

    $actions = $event->getValue('actions');
    $actions[] = $flag_action;
    $event->setValue('actions', $actions);
  }

}

