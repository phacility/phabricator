<?php

/**
 * This event listener is tasked with probably one of the most important
 * missions in this world: Adding a Conpherence button to a hovercard.
 *
 * Handle with care when modifying!
 *
 * @task event
 */
final class ConpherenceHovercardEventListener extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERHOVERCARD);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_DIDRENDERHOVERCARD:
        $this->handleHovercardEvent($event);
      break;
    }
  }

  private function handleHovercardEvent($event) {
    $hovercard = $event->getValue('hovercard');
    $user = $event->getValue('object');

    if (!($user instanceof PhabricatorUser)) {
      return;
    }

    $conpherence_uri = new PhutilURI(
      '/conpherence/new/?participant='.$user->getPHID());
    $name = pht('Send a Message');
    $hovercard->addAction($name, $conpherence_uri, true);

    $event->setValue('hovercard', $hovercard);
  }

}
