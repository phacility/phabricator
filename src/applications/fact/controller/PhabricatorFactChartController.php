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

    if ($is_chart_mode) {
      return $this->newChartResponse();
    }

    $datasets = array();
    foreach ($functions as $function) {
      $function->loadData();

      $points = $function->getDatapoints(2000);

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
