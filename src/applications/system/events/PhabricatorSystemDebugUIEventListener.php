<?php

final class PhabricatorSystemDebugUIEventListener
  extends PhabricatorEventListener {

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
    $viewer = $event->getUser();
    $object = $event->getValue('object');

    if (!PhabricatorEnv::getEnvConfig('phabricator.developer-mode')) {
      return;
    }

    if (!$object || !$object->getPHID()) {
      // If we have no object, or the object doesn't have a PHID, we can't
      // do anything useful.
      return;
    }

    $phid = $object->getPHID();

    $submenu = array();

    $submenu[] = id(new PhabricatorActionView())
      ->setIcon('fa-asterisk')
      ->setName(pht('View Handle'))
      ->setHref(urisprintf('/search/handle/%s/', $phid))
      ->setWorkflow(true);

    $submenu[] = id(new PhabricatorActionView())
      ->setIcon('fa-address-card-o')
      ->setName(pht('View Hovercard'))
      ->setHref(urisprintf('/search/hovercard/?names=%s', $phid));

    if ($object instanceof DifferentialRevision) {
      $submenu[] = id(new PhabricatorActionView())
        ->setIcon('fa-database')
        ->setName(pht('View Affected Path Index'))
        ->setHref(
          urisprintf(
            '/differential/revision/paths/%s/',
            $object->getID()));
    }

    $developer_action = id(new PhabricatorActionView())
      ->setName(pht('Advanced/Developer...'))
      ->setIcon('fa-magic')
      ->setOrder(9001)
      ->setSubmenu($submenu);

    $actions = $event->getValue('actions');
    $actions[] = $developer_action;
    $event->setValue('actions', $actions);
  }

}
