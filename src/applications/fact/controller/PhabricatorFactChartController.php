<?php

final class PhabricatorFactChartController extends PhabricatorFactController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    // When drawing a chart, we send down a placeholder piece of HTML first,
    // then fetch the data via async request. Determine if we're drawing
    // the structure or actually pulling the data.
    $mode = $request->getURIData('mode');
    $is_chart_mode = ($mode === 'chart');
    $is_draw_mode = ($mode === 'draw');

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

    if ($is_chart_mode) {
      return $this->newChartResponse();
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
      $linear[$x] = round(count($points) * (($x - $x_min) / $x_range));
    }
    $datasets[] = array(
      'x' => array_keys($linear),
      'y' => array_values($linear),
      'color' => '#0000ff',
    );

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

    $chart_data = array(
      'datasets' => $datasets,
      'xMin' => $x_min,
      'xMax' => $x_max,
      'yMin' => $y_min,
      'yMax' => $y_max,
    );

    return id(new AphrontAjaxResponse())->setContent($chart_data);
  }

  private function newChartResponse() {
    $request = $this->getRequest();
    $chart_node_id = celerity_generate_unique_node_id();

    $chart_view = phutil_tag(
      'div',
      array(
        'id' => $chart_node_id,
        'style' => 'background: #ffffff; '.
                   'height: 480px; ',
      ),
      '');

    $data_uri = $request->getRequestURI();
    $data_uri->setPath('/fact/draw/');

    Javelin::initBehavior(
      'line-chart',
      array(
        'chartNodeID' => $chart_node_id,
        'dataURI' => (string)$data_uri,
      ));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Chart'))
      ->appendChild($chart_view);

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
