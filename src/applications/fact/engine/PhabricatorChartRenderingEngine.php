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
        'class' => 'chart-hardpoint',
      ));

    $data_uri = urisprintf('/fact/chart/%s/draw/', $chart_key);

    Javelin::initBehavior(
      'line-chart',
      array(
        'chartNodeID' => $chart_node_id,
        'dataURI' => (string)$data_uri,
      ));

    return $chart_view;
  }

  public function newTabularView() {
    $viewer = $this->getViewer();
    $tabular_data = $this->newTabularData();

    $ref_keys = array();
    foreach ($tabular_data['datasets'] as $tabular_dataset) {
      foreach ($tabular_dataset as $function) {
        foreach ($function['data'] as $point) {
          foreach ($point['refs'] as $ref) {
            $ref_keys[$ref] = $ref;
          }
        }
      }
    }

    $chart = $this->getStoredChart();

    $ref_map = array();
    foreach ($chart->getDatasets() as $dataset) {
      foreach ($dataset->getFunctions() as $function) {
        // If we aren't looking for anything else, bail out.
        if (!$ref_keys) {
          break 2;
        }

        $function_refs = $function->loadRefs($ref_keys);

        $ref_map += $function_refs;

        // Remove the ref keys that we found data for from the list of keys
        // we are looking for. If any function gives us data for a given ref,
        // that's satisfactory.
        foreach ($function_refs as $ref_key => $ref_data) {
          unset($ref_keys[$ref_key]);
        }
      }
    }

    $phids = array();
    foreach ($ref_map as $ref => $ref_data) {
      if (isset($ref_data['objectPHID'])) {
        $phids[] = $ref_data['objectPHID'];
      }
    }

    $handles = $viewer->loadHandles($phids);

    $tabular_view = array();
    foreach ($tabular_data['datasets'] as $tabular_data) {
      foreach ($tabular_data as $function) {
        $rows = array();
        foreach ($function['data'] as $point) {
          $ref_views = array();

          $xv = date('Y-m-d h:i:s', $point['x']);
          $yv = $point['y'];

          $point_refs = array();
          foreach ($point['refs'] as $ref) {
            if (!isset($ref_map[$ref])) {
              continue;
            }
            $point_refs[$ref] = $ref_map[$ref];
          }

          if (!$point_refs) {
            $rows[] = array(
              $xv,
              $yv,
              null,
              null,
              null,
            );
          } else {
            foreach ($point_refs as $ref => $ref_data) {
              $ref_value = $ref_data['value'];
              $ref_link = $handles[$ref_data['objectPHID']]
                ->renderLink();

              $view_uri = urisprintf(
                '/fact/object/%s/',
                $ref_data['objectPHID']);

              $ref_button = id(new PHUIButtonView())
                ->setIcon('fa-table')
                ->setTag('a')
                ->setColor('grey')
                ->setHref($view_uri)
                ->setText(pht('View Data'));

              $rows[] = array(
                $xv,
                $yv,
                $ref_value,
                $ref_link,
                $ref_button,
              );

              $xv = null;
              $yv = null;
            }
          }
        }

        $table = id(new AphrontTableView($rows))
          ->setHeaders(
            array(
              pht('X'),
              pht('Y'),
              pht('Raw'),
              pht('Refs'),
              null,
            ))
          ->setColumnClasses(
            array(
              'n',
              'n',
              'n',
              'wide',
              null,
            ));

        $tabular_view[] = id(new PHUIObjectBoxView())
          ->setHeaderText(pht('Function'))
          ->setTable($table);
      }
    }

    return $tabular_view;
  }

  public function newChartData() {
    return $this->newWireData(false);
  }

  public function newTabularData() {
    return $this->newWireData(true);
  }

  private function newWireData($is_tabular) {
    $chart = $this->getStoredChart();
    $chart_key = $chart->getChartKey();

    $chart_engine = PhabricatorChartEngine::newFromChart($chart)
      ->setViewer($this->getViewer());
    $chart_engine->buildChart($chart);

    $datasets = $chart->getDatasets();

    $functions = array();
    foreach ($datasets as $dataset) {
      foreach ($dataset->getFunctions() as $function) {
        $functions[] = $function;
      }
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

    $domain = $this->getDomain($functions);

    $axis = id(new PhabricatorChartAxis())
      ->setMinimumValue($domain->getMin())
      ->setMaximumValue($domain->getMax());

    $data_query = id(new PhabricatorChartDataQuery())
      ->setMinimumValue($domain->getMin())
      ->setMaximumValue($domain->getMax())
      ->setLimit(2000);

    $wire_datasets = array();
    $ranges = array();
    foreach ($datasets as $dataset) {
      if ($is_tabular) {
        $display_data = $dataset->getTabularDisplayData($data_query);
      } else {
        $display_data = $dataset->getChartDisplayData($data_query);
      }

      $ranges[] = $display_data->getRange();
      $wire_datasets[] = $display_data->getWireData();
    }

    $range = $this->getRange($ranges);

    $chart_data = array(
      'datasets' => $wire_datasets,
      'xMin' => $domain->getMin(),
      'xMax' => $domain->getMax(),
      'yMin' => $range->getMin(),
      'yMax' => $range->getMax(),
    );

    return $chart_data;
  }

  private function getDomain(array $functions) {
    $domains = array();
    foreach ($functions as $function) {
      $domains[] = $function->getDomain();
    }

    $domain = PhabricatorChartInterval::newFromIntervalList($domains);

    // If we don't have any domain data from the actual functions, pick a
    // plausible domain automatically.

    if ($domain->getMax() === null) {
      $domain->setMax(PhabricatorTime::getNow());
    }

    if ($domain->getMin() === null) {
      $domain->setMin($domain->getMax() - phutil_units('365 days in seconds'));
    }

    return $domain;
  }

  private function getRange(array $ranges) {
    $range = PhabricatorChartInterval::newFromIntervalList($ranges);

    // Start the Y axis at 0 unless the chart has negative values.
    $min = $range->getMin();
    if ($min === null || $min >= 0) {
      $range->setMin(0);
    }

    // If there's no maximum value, just pick a plausible default.
    $max = $range->getMax();
    if ($max === null) {
      $range->setMax($range->getMin() + 100);
    }

    return $range;
  }

}
