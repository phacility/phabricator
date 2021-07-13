<?php
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

/**
 * Adds a `View in Lando` link on the revision page
 */

final class LandoLinkEventListener extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS);
  }

  public function handleEvent(PhutilEvent $event) {
    if ($event->getType() == PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS) {
        $this->handleActionEvent($event);
    }
  }

  private function handleActionEvent($event) {
    $object = $event->getValue('object');

    if (!($object && $object->getPHID() && $object instanceof DifferentialRevision)) {
      return;
    }

    $lando_uri = PhabricatorEnv::getEnvConfig('lando-ui.url');
    if (!$lando_uri) {
      return;
    }

    $active_diff = $object->getActiveDiff();
    if (!$active_diff) {
      return;
    }

    // View stack in Lando Action
    $lando_stack_uri = (string) id(new PhutilURI($lando_uri))
      ->setPath('/D' . $object->getID() . '/');

    $action = id(new PhabricatorActionView())
      ->setHref($lando_stack_uri)
      ->setName(pht('View Stack in Lando'))
      ->setIcon('fa-link')
      ->setDisabled(!$object->isAccepted());

    $actions = $event->getValue('actions');
    $actions[] = $action;

    $event->setValue('actions', $actions);
  }

}
