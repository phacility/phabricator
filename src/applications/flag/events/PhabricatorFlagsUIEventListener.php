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

    if (($object instanceof PhabricatorUser)) {
      // Although there's no real reason not to let you flag users, it also
      // makes less conceptual sense than flaging other types of objects. For
      // now, don't allow it.
      return;
    }

    $flag = PhabricatorFlagQuery::loadUserFlag($user, $object->getPHID());

    if ($flag) {
      $color = PhabricatorFlagColor::getColorName($flag->getColor());
      $flag_action = id(new PhabricatorActionView())
        ->setWorkflow(true)
        ->setHref('/flag/delete/'.$flag->getID().'/')
        ->setName(pht('Remove %s Flag', $color))
        ->setIcon('flag-'.$flag->getColor());
    } else {
      $flag_action = id(new PhabricatorActionView())
        ->setWorkflow(true)
        ->setHref('/flag/edit/'.$object->getPHID().'/')
        ->setName(pht('Flag For Later'))
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

