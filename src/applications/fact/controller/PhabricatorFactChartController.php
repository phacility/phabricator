<?php

final class PhabricatorFactChartController extends PhabricatorFactController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $series = $request->getStr('y1');

    $facts = PhabricatorFact::getAllFacts();
    $fact = idx($facts, $series);

    if (!$fact) {
      return new Aphront404Response();
    }

    $key_id = id(new PhabricatorFactKeyDimension())
      ->newDimensionID($fact->getKey());
    if (!$key_id) {
      return new Aphront404Response();
    }

    $table = $fact->newDatapoint();
    $conn_r = $table->establishConnection('r');
    $table_name = $table->getTableName();

    $data = queryfx_all(
      $conn_r,
      'SELECT value, epoch FROM %T WHERE keyID = %d ORDER BY epoch ASC',
      $table_name,
      $key_id);

    $points = array();
    $sum = 0;
    foreach ($data as $key => $row) {
      $sum += (int)$row['value'];
      $points[(int)$row['epoch']] = $sum;
    }

    if (!$points) {
      throw new Exception('No data to show!');
    }

    // Limit amount of data passed to browser.
    $count = count($points);
    $limit = 2000;
    if ($count > $limit) {
      $i = 0;
      $every = ceil($count / $limit);
      foreach ($points as $epoch => $sum) {
        $i++;
        if ($i % $every && $i != $count) {
          unset($points[$epoch]);
        }
      }
    }

    $datasets = array();

    $datasets[] = array(
      'x' => array_keys($points),
      'y' => array_values($points),
      'color' => '#ff0000',
    );


    // Add a dummy "y = x" dataset to prove we can draw multiple datasets.
    $x_min = min(array_keys($points));
    $x_max = max(array_keys($points));
    $x_range = ($x_max - $x_min) / 4;
    $linear = array();
    foreach ($points as $x => $y) {
      $linear[$x] = count($points) * (($x - $x_min) / $x_range);
    }
    $datasets[] = array(
      'x' => array_keys($linear),
      'y' => array_values($linear),
      'color' => '#0000ff',
    );


    $id = celerity_generate_unique_node_id();
    $chart = phutil_tag(
      'div',
      array(
        'id' => $id,
        'style' => 'background: #ffffff; '.
                   'height: 480px; ',
      ),
      '');

    require_celerity_resource('d3');

    $y_min = 0;
    $y_max = 0;
    $x_min = null;
    $x_max = 0;
    foreach ($datasets as $dataset) {
      if (!$dataset['y']) {
        continue;
      }

      $y_min = min($y_min, min($dataset['y']));
      $y_max = max($y_max, max($dataset['y']));

      if ($x_min === null) {
        $x_min = min($dataset['x']);
      } else {
        $x_min = min($x_min, min($dataset['x']));
      }

      $x_max = max($x_max, max($dataset['x']));
    }

    Javelin::initBehavior(
      'line-chart',
      array(
        'hardpoint' => $id,
        'datasets' => $datasets,
        'xMin' => $x_min,
        'xMax' => $x_max,
        'yMin' => $y_min,
        'yMax' => $y_max,
        'xformat' => 'epoch',
      ));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Count of %s', $fact->getName()))
      ->appendChild($chart);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Chart'))
      ->setBorder(true);

    $title = pht('Chart');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($box);

  }

}
