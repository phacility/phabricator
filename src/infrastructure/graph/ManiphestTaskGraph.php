<?php

final class ManiphestTaskGraph
  extends PhabricatorObjectGraph {

  protected function getEdgeTypes() {
    return array(
      ManiphestTaskDependedOnByTaskEdgeType::EDGECONST,
      ManiphestTaskDependsOnTaskEdgeType::EDGECONST,
    );
  }

  protected function getParentEdgeType() {
    return ManiphestTaskDependsOnTaskEdgeType::EDGECONST;
  }

  protected function newQuery() {
    return new ManiphestTaskQuery();
  }

  protected function isClosed($object) {
    return $object->isClosed();
  }

  protected function newTableRow($phid, $object, $trace) {
    $viewer = $this->getViewer();

    if ($object) {
      $status = $object->getStatus();
      $priority = $object->getPriority();
      $status_icon = ManiphestTaskStatus::getStatusIcon($status);
      $status_name = ManiphestTaskStatus::getTaskStatusName($status);

      $priority_color = ManiphestTaskPriority::getTaskPriorityColor($priority);
      if ($object->isClosed()) {
        $priority_color = 'grey';
      }

      $status = array(
        id(new PHUIIconView())->setIcon($status_icon, $priority_color),
        ' ',
        $status_name,
      );

      $owner_phid = $object->getOwnerPHID();
      if ($owner_phid) {
        $assigned = $viewer->renderHandle($owner_phid);
      } else {
        $assigned = phutil_tag('em', array(), pht('None'));
      }

      $link = phutil_tag(
        'a',
        array(
          'href' => $object->getURI(),
        ),
        $object->getTitle());

      $link = array(
        $object->getMonogram(),
        ' ',
        $link,
      );
    } else {
      $status = null;
      $assigned = null;
      $link = $viewer->renderHandle($phid);
    }

    $link = AphrontTableView::renderSingleDisplayLine($link);

    return array(
      $trace,
      $status,
      $assigned,
      $link,
    );
  }

  protected function newTable(AphrontTableView $table) {
    return $table
      ->setHeaders(
        array(
          null,
          pht('Status'),
          pht('Assigned'),
          pht('Task'),
        ))
      ->setColumnClasses(
        array(
          'threads',
          'graph-status',
          null,
          'wide pri object-link',
        ));
  }

}
