<?php
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

/**
 * Adds a `New Changes` link on the revision page
 */

final class NewChangesLinkEventListener extends PhabricatorEventListener {

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

    if (!($object instanceof DifferentialRevision)) {
      return;
    }

    $active_diff = $object->getActiveDiff();
    if (!$active_diff) {
      return;
    }

    $path = $_SERVER['REQUEST_URI'];
    $isOnNewChangesPage = substr($path, -strlen('/new/')) == '/new/';
    $isLoggedIn = (bool) $event->getUser()->isLoggedIn();

    // View new changes action
    $action = (new PhabricatorActionView())
      ->setHref(urisprintf('%s/new/', $object->getURI()))
      ->setName(pht('New Changes'))
      ->setIcon('fa-history')
      ->setDisabled($isOnNewChangesPage || !$isLoggedIn);

    $actions = $event->getValue('actions');
    $actions[] = $action;

    $event->setValue('actions', $actions);
  }

}
