<?php

final class PhabricatorFactChartController extends PhabricatorFactController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $table = new PhabricatorFactRaw();
    $conn_r = $table->establishConnection('r');
    $table_name = $table->getTableName();

    $series = $request->getStr('y1');

    $specs = PhabricatorFactSpec::newSpecsForFactTypes(
      PhabricatorFactEngine::loadAllEngines(),
      array($series));
    $spec = idx($specs, $series);

    $data = queryfx_all(
      $conn_r,
      'SELECT valueX, epoch FROM %T WHERE factType = %s ORDER BY epoch ASC',
      $table_name,
      $series);

    $points = array();
    $sum = 0;
    foreach ($data as $key => $row) {
      $sum += (int)$row['valueX'];
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
      ->setHeaderText(pht('Count of %s', $spec->getName()))
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
