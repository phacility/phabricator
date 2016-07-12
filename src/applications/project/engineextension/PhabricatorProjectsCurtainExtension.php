<?php

final class PhabricatorProjectsCurtainExtension
  extends PHUICurtainExtension {

  const EXTENSIONKEY = 'projects.projects';

  public function shouldEnableForObject($object) {
    return ($object instanceof PhabricatorProjectInterface);
  }

  public function getExtensionApplication() {
    return new PhabricatorProjectApplication();
  }

  public function buildCurtainPanel($object) {
    $viewer = $this->getViewer();

    $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);

    $has_projects = (bool)$project_phids;
    $project_phids = array_reverse($project_phids);
    $handles = $viewer->loadHandles($project_phids);

    // If this object can appear on boards, build the workboard annotations.
    // Some day, this might be a generic interface. For now, only tasks can
    // appear on boards.
    $can_appear_on_boards = ($object instanceof ManiphestTask);

    $annotations = array();
    if ($has_projects && $can_appear_on_boards) {
      $engine = id(new PhabricatorBoardLayoutEngine())
        ->setViewer($viewer)
        ->setBoardPHIDs($project_phids)
        ->setObjectPHIDs(array($object->getPHID()))
        ->executeLayout();

      // TDOO: Generalize this UI and move it out of Maniphest.
      require_celerity_resource('maniphest-task-summary-css');

      foreach ($project_phids as $project_phid) {
        $handle = $handles[$project_phid];

        $columns = $engine->getObjectColumns(
          $project_phid,
          $object->getPHID());

        $annotation = array();
        foreach ($columns as $column) {
          $project_id = $column->getProject()->getID();

          $column_name = pht('(%s)', $column->getDisplayName());
          $column_link = phutil_tag(
            'a',
            array(
              'href' => "/project/board/{$project_id}/",
              'class' => 'maniphest-board-link',
            ),
            $column_name);

          $annotation[] = $column_link;
        }

        if ($annotation) {
          $annotations[$project_phid] = array(
            ' ',
            phutil_implode_html(', ', $annotation),
          );
        }
      }

    }

    if ($has_projects) {
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
