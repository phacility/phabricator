<?php

final class PhabricatorRolesCurtainExtension
  extends PHUICurtainExtension {

  const EXTENSIONKEY = 'roles.roles';

  public function shouldEnableForObject($object) {
    return ($object instanceof PhabricatorRoleInterface);
  }

  public function getExtensionApplication() {
    return new PhabricatorRoleApplication();
  }

  public function buildCurtainPanel($object) {
    $viewer = $this->getViewer();

    $role_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorRoleObjectHasRoleEdgeType::EDGECONST);

    $has_roles = (bool)$role_phids;
    $role_phids = array_reverse($role_phids);
    $handles = $viewer->loadHandles($role_phids);

    // If this object can appear on boards, build the workboard annotations.
    // Some day, this might be a generic interface. For now, only tasks can
    // appear on boards.
    $can_appear_on_boards = ($object instanceof ManiphestTask);

    $annotations = array();
    if ($has_roles && $can_appear_on_boards) {
      $engine = id(new PhabricatorRoleBoardLayoutEngine())
        ->setViewer($viewer)
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

    if ($has_roles) {
      $list = id(new PHUIHandleTagListView())
        ->setHandles($handles)
        ->setAnnotations($annotations)
        ->setShowHovercards(true);
    } else {
      $list = phutil_tag('em', array(), pht('None'));
    }

    return $this->newPanel()
      ->setHeaderText(pht('Tags'))
      ->setOrder(10000)
      ->appendChild($list);
  }

}
