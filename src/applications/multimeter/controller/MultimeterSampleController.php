<?php

final class MultimeterSampleController extends MultimeterController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $table = new MultimeterEvent();
    $conn = $table->establishConnection('r');
    $data = queryfx_all(
      $conn,
      'SELECT * FROM %T ORDER BY id DESC LIMIT 100',
      $table->getTableName());

    $this->loadDimensions($data);

    $rows = array();
    foreach ($data as $row) {
      $rows[] = array(
        $row['id'],
        $row['requestKey'],
        $this->getViewerDimension($row['eventViewerID'])->getName(),
        $this->getContextDimension($row['eventContextID'])->getName(),
        $this->getHostDimension($row['eventHostID'])->getName(),
        MultimeterEvent::getEventTypeName($row['eventType']),
        $this->getLabelDimension($row['eventLabelID'])->getName(),
        MultimeterEvent::formatResourceCost(
          $viewer,
          $row['eventType'],
          $row['resourceCost']),
        $row['sampleRate'],
        phabricator_datetime($row['epoch'], $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('ID'),
          pht('Request'),
          pht('Viewer'),
          pht('Context'),
          pht('Host'),
          pht('Type'),
          pht('Label'),
          pht('Cost'),
          pht('Rate'),
          pht('Epoch'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          null,
          null,
          null,
          null,
          'wide',
          'n',
          'n',
          null,
        ));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Samples'))
      ->appendChild($table);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Samples'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => pht('Samples'),
      ));
  }

}
