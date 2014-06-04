<?php

final class PhabricatorProjectUIEventListener
  extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES:
        $this->handlePropertyEvent($event);
        break;
    }
  }

  private function handlePropertyEvent($event) {
    $user = $event->getUser();
    $object = $event->getValue('object');

    if (!$object || !$object->getPHID()) {
      // No object, or the object has no PHID yet..
      return;
    }

    if (!($object instanceof PhabricatorProjectInterface)) {
      // This object doesn't have projects.
      return;
    }

    $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorEdgeConfig::TYPE_OBJECT_HAS_PROJECT);
    if ($project_phids) {
      $project_phids = array_reverse($project_phids);
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($user)
        ->withPHIDs($project_phids)
        ->execute();
    } else {
      $handles = array();
    }

    if ($handles) {
      $list = array();
      foreach ($handles as $handle) {
        $list[] = $handle->renderLink();
      }
      $list = phutil_implode_html(phutil_tag('br'), $list);
    } else {
      $list = phutil_tag('em', array(), pht('None'));
    }

    $view = $event->getValue('view');
    $view->addProperty(pht('Projects'), $list);
  }

}
