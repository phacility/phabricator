<?php

final class DifferentialRevisionGraph
  extends PhabricatorObjectGraph {

  protected function getEdgeTypes() {
    return array(
      DifferentialRevisionDependsOnRevisionEdgeType::EDGECONST,
      DifferentialRevisionDependedOnByRevisionEdgeType::EDGECONST,
    );
  }

  protected function getParentEdgeType() {
    return DifferentialRevisionDependsOnRevisionEdgeType::EDGECONST;
  }

  protected function newQuery() {
    return new DifferentialRevisionQuery();
  }

  protected function isClosed($object) {
    return $object->isClosed();
  }

  protected function newTableRow($phid, $object, $trace) {
    $viewer = $this->getViewer();

    if ($object) {
      $status_icon = $object->getStatusIcon();
      $status_name = $object->getStatusDisplayName();

      $status = array(
        id(new PHUIIconView())->setIcon($status_icon),
        ' ',
        $status_name,
      );

      $author = $viewer->renderHandle($object->getAuthorPHID());
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
      $author = null;
      $link = $viewer->renderHandle($phid);
    }

    $link = AphrontTableView::renderSingleDisplayLine($link);

    return array(
      $trace,
      $status,
      $author,
      $link,
    );
  }

  protected function newTable(AphrontTableView $table) {
    return $table
      ->setHeaders(
        array(
          null,
          pht('Status'),
          pht('Author'),
          pht('Revision'),
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
