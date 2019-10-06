<?php

final class PhabricatorFactChartController
  extends PhabricatorFactController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $chart_key = $request->getURIData('chartKey');
    if (!$chart_key) {
      return new Aphront404Response();
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

    $want_data = $is_draw_mode;

    // In developer mode, always pull the data in the main request. We'll
    // throw it away if we're just drawing the chart frame, but this currently
    // makes errors quite a bit easier to debug.
    if (PhabricatorEnv::getEnvConfig('phabricator.developer-mode')) {
      $want_data = true;
    }

    if ($want_data) {
      $chart_data = $engine->newChartData();
      if ($is_draw_mode) {
        return id(new AphrontAjaxResponse())->setContent($chart_data);
      }
    }

    $chart_view = $engine->newChartView();

    return $this->newChartResponse($chart_view);
  }

  private function newChartResponse($chart_view) {
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
        ));
  }

}
