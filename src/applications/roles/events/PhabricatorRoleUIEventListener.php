<?php

final class PhabricatorRoleUIEventListener
  extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES);
  }

  public function handleEvent(PhutilEvent $event) {
    $object = $event->getValue('object');

    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES:
        // Hacky solution so that property list view on Diffusion
        // commits shows build status, but not Roles, Subscriptions,
        // or Tokens.
        if ($object instanceof PhabricatorRepositoryCommit) {
          return;
        }
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

    if (!($object instanceof PhabricatorRoleInterface)) {
      // This object doesn't have roles.
      return;
    }

    $role_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorRoleObjectHasRoleEdgeType::EDGECONST);
    if ($role_phids) {
      $role_phids = array_reverse($role_phids);
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($user)
        ->withPHIDs($role_phids)
        ->execute();
    } else {
      $handles = array();
    }

    // If this object can appear on boards, build the workboard annotations.
    // Some day, this might be a generic interface. For now, only tasks can
    // appear on boards.
    $can_appear_on_boards = ($object instanceof ManiphestTask);

    $annotations = array();
    if ($handles && $can_appear_on_boards) {
      $engine = id(new PhabricatorRoleBoardLayoutEngine())
        ->setViewer($user)
        ->setBoardPHIDs($role_phids)
        ->setObjectPHIDs(array($object->getPHID()))
        ->executeLayout();

      // TDOO: Generalize this UI and move it out of Maniphest.
      require_celerity_resource('maniphest-task-summary-css');

      foreach ($role_phids as $role_phid) {
        $handle = $handles[$role_phid];

        $columns = $engine->getObjectColumns(
          $role_phid,
          $object->getPHID());

        $annotation = array();
        foreach ($columns as $column) {
          $role_id = $column->getRole()->getID();

          $column_name = pht('(%s)', $column->getDisplayName());
          $column_link = phutil_tag(
            'a',
            array(
              'href' => $column->getWorkboardURI(),
              'class' => 'maniphest-board-link',
            ),
            $column_name);

          $annotation[] = $column_link;
        }

        if ($annotation) {
          $annotations[$role_phid] = array(
            ' ',
            phutil_implode_html(', ', $annotation),
          );
        }
      }

    }

    if ($handles) {
      $list = id(new PHUIHandleTagListView())
        ->setHandles($handles)
        ->setAnnotations($annotations)
        ->setShowHovercards(true);
    } else {
      $list = phutil_tag('em', array(), pht('None'));
    }

    $view = $event->getValue('view');
    $view->addProperty(pht('Roles'), $list);
  }

}
