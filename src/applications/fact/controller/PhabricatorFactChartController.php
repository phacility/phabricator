<?php

final class PhabricatorFactChartController extends PhabricatorFactController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $chart_key = $request->getURIData('chartKey');
    if ($chart_key === null) {
      return $this->newDemoChart();
    }

    $engine = id(new PhabricatorChartRenderingEngine())
      ->setViewer($viewer);

    $chart = $engine->loadChart($chart_key);
    if (!$chart) {
      return new Aphront404Response();
    }

    // When drawing a chart, we send down a placeholder piece of HTML first,
    // then fetch the data via async request. Determine if we're drawing
    // the structure or actually pulling the data.
    $mode = $request->getURIData('mode');
    $is_draw_mode = ($mode === 'draw');

    // TODO: For now, always pull the data. We'll throw it away if we're just
    // drawing the frame, but this makes errors easier to debug.
    $chart_data = $engine->newChartData();

    if ($is_draw_mode) {
      return id(new AphrontAjaxResponse())->setContent($chart_data);
    }

    $chart_view = $engine->newChartView();
    $tabular_view = $engine->newTabularView();

    return $this->newChartResponse($chart_view, $tabular_view);
  }

  private function newChartResponse($chart_view, $tabular_view) {
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
      ->appendChild(
        array(
          $box,
          $tabular_view,
        ));
  }

  private function newDemoChart() {
    $viewer = $this->getViewer();

    $chart = id(new PhabricatorDemoChartEngine())
      ->setViewer($viewer)
      ->newStoredChart();

    return id(new AphrontRedirectResponse())->setURI($chart->getURI());
  }

}
