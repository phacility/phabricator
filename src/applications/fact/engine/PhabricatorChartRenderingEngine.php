<?php

final class PhabricatorChartRenderingEngine
  extends Phobject {

  private $viewer;
  private $chart;
  private $storedChart;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setChart(PhabricatorFactChart $chart) {
    $this->chart = $chart;
    return $this;
  }

  public function getChart() {
    return $this->chart;
  }

  public function loadChart($chart_key) {
    $chart = id(new PhabricatorFactChart())->loadOneWhere(
      'chartKey = %s',
      $chart_key);

    if ($chart) {
      $this->setChart($chart);
    }

    return $chart;
  }

  public static function getChartURI($chart_key) {
    return id(new PhabricatorFactChart())
      ->setChartKey($chart_key)
      ->getURI();
  }

  public function getStoredChart() {
    if (!$this->storedChart) {
      $chart = $this->getChart();
      $chart_key = $chart->getChartKey();
      if (!$chart_key) {
        $chart_key = $chart->newChartKey();

        $stored_chart = id(new PhabricatorFactChart())->loadOneWhere(
          'chartKey = %s',
          $chart_key);
        if ($stored_chart) {
          $chart = $stored_chart;
        } else {
          $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

          try {
            $chart->save();
          } catch (AphrontDuplicateKeyQueryException $ex) {
            $chart = id(new PhabricatorFactChart())->loadOneWhere(
              'chartKey = %s',
              $chart_key);
            if (!$chart) {
              throw new Exception(
                pht(
                  'Failed to load chart with key "%s" after key collision. '.
                  'This should not be possible.',
                  $chart_key));
            }
          }

          unset($unguarded);
        }
        $this->setChart($chart);
      }

      $this->storedChart = $chart;
    }

    return $this->storedChart;
  }

  public function newChartView() {
    $chart = $this->getStoredChart();
    $chart_key = $chart->getChartKey();

    $chart_node_id = celerity_generate_unique_node_id();

    $chart_view = phutil_tag(
      'div',
      array(
        'id' => $chart_node_id,
        'style' => 'background: #ffffff; '.
                   'height: 480px; ',
      ),
      '');

    $data_uri = urisprintf('/fact/chart/%s/draw/', $chart_key);

    Javelin::initBehavior(
      'line-chart',
      array(
        'chartNodeID' => $chart_node_id,
        'dataURI' => (string)$data_uri,
      ));

    return $chart_view;
  }

  public function newChartData() {
    $chart = $this->getStoredChart();
    $chart_key = $chart->getChartKey();

    $datasets = $chart->getDatasets();

    $functions = array();
    foreach ($datasets as $dataset) {
      $functions[] = $dataset->getFunction();
    }

    $subfunctions = array();
    foreach ($functions as $function) {
      foreach ($function->getSubfunctions() as $subfunction) {
        $subfunctions[] = $subfunction;
      }
    }

    foreach ($subfunctions as $subfunction) {
      $subfunction->loadData();
    }

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
      $points = $function->newDatapoints($data_query);

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

    return $chart_data;
  }

  private function getDomain(array $functions) {
    $domain_min_list = null;
    $domain_max_list = null;

    foreach ($functions as $function) {
      $domain = $function->getDomain();

      list($function_min, $function_max) = $domain;

      if ($function_min !== null) {
        $domain_min_list[] = $function_min;
      }

      if ($function_max !== null) {
        $domain_max_list[] = $function_max;
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
