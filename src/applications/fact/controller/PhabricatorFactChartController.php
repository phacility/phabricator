<?php

final class PhabricatorFactChartController extends PhabricatorFactController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

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
      // NOTE: Raphael crashes Safari if you hand it series with no points.
      throw new Exception(pht('No data to show!'));
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
        'style' => 'border: 1px solid #6f6f6f; '.
                   'margin: 1em 2em; '.
                   'background: #ffffff; '.
                   'height: 400px; ',
      ),
      '');

    require_celerity_resource('raphael-core');
    require_celerity_resource('raphael-g');
    require_celerity_resource('raphael-g-line');

    Javelin::initBehavior('line-chart', array(
      'hardpoint' => $id,
      'x' => array($x),
      'y' => array($y),
      'xformat' => 'epoch',
      'colors' => array('#0000ff'),
    ));

    $panel = new PHUIObjectBoxView();
    $panel->setHeaderText(pht('Count of %s', $spec->getName()));
    $panel->appendChild($chart);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Chart'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $panel,
      ),
      array(
        'title' => pht('Chart'),
      ));
  }

}
