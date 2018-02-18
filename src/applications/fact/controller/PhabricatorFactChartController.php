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

    $x = array_keys($points);
    $y = array_values($points);

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

    Javelin::initBehavior('line-chart', array(
      'hardpoint' => $id,
      'x' => array($x),
      'y' => array($y),
      'xformat' => 'epoch',
      'colors' => array('#0000ff'),
    ));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Count of %s', $fact->getName()))
      ->appendChild($chart);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Chart'));

    $title = pht('Chart');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($box);

  }

}
