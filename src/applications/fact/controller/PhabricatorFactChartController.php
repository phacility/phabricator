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

    $functions = array();

    $functions[] = id(new PhabricatorFactChartFunction())
      ->setArguments(array('tasks.count.create'));

    $functions[] = id(new PhabricatorFactChartFunction())
      ->setArguments(array('tasks.open-count.create'));

    $x_function = id(new PhabricatorXChartFunction())
      ->setArguments(array());

    $functions[] = id(new PhabricatorConstantChartFunction())
      ->setArguments(array(360));

    $functions[] = id(new PhabricatorSinChartFunction())
      ->setArguments(array($x_function));

    $cos_function = id(new PhabricatorCosChartFunction())
      ->setArguments(array($x_function));

    $functions[] = id(new PhabricatorShiftChartFunction())
      ->setArguments(
        array(
          array(
            'scale',
            array(
              'cos',
              array(
                'scale',
                array('x'),
                0.001,
              ),
            ),
            10,
          ),
          200,
        ));

    list($domain_min, $domain_max) = $this->getDomain($functions);

    $axis = id(new PhabricatorChartAxis())
      ->setMinimumValue($domain_min)
      ->setMaximumValue($domain_max);

    $data_query = id(new PhabricatorChartDataQuery())
      ->setMinimumValue($domain_min)
      ->setMaximumValue($domain_max)
      ->setLimit(2000);

    $datasets = array();
    foreach ($functions as $function) {
      $function->setXAxis($axis);

      $function->loadData();

      $points = $function->getDatapoints($data_query);

      $x = array();
      $y = array();

      foreach ($points as $point) {
        $x[] = $point['x'];
        $y[] = $point['y'];
      }

      $datasets[] = array(
        'x' => $x,
        'y' => $y,
        'color' => '#ff00ff',
      );
    }


    $y_min = 0;
    $y_max = 0;
    foreach ($datasets as $dataset) {
      if (!$dataset['y']) {
        continue;
      }

      $y_min = min($y_min, min($dataset['y']));
      $y_max = max($y_max, max($dataset['y']));
    }

    $chart_data = array(
      'datasets' => $datasets,
      'xMin' => $domain_min,
      'xMax' => $domain_max,
      'yMin' => $y_min,
      'yMax' => $y_max,
    );

    // TODO: Move this back up, it's just down here for now to make
    // debugging easier so the main page throws a more visible exception when
    // something goes wrong.
    if ($is_chart_mode) {
      return $this->newChartResponse();
    }

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

  private function getDomain(array $functions) {
    $domain_min_list = null;
    $domain_max_list = null;
    foreach ($functions as $function) {
      if ($function->hasDomain()) {
        $domain = $function->getDomain();

        list($domain_min, $domain_max) = $domain;

        if ($domain_min !== null) {
          $domain_min_list[] = $domain_min;
        }

        if ($domain_max !== null) {
          $domain_max_list[] = $domain_max;
        }
      }
    }

    $domain_min = null;
    $domain_max = null;

    if ($domain_min_list) {
      $domain_min = min($domain_min_list);
    }

    if ($domain_max_list) {
      $domain_max = max($domain_max_list);
    }

    // If we don't have any domain data from the actual functions, pick a
    // plausible domain automatically.

    if ($domain_max === null) {
      $domain_max = PhabricatorTime::getNow();
    }

    if ($domain_min === null) {
      $domain_min = $domain_max - phutil_units('365 days in seconds');
    }

    return array($domain_min, $domain_max);
  }


}
