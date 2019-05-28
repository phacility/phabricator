<?php

final class PhabricatorChartStackedAreaDataset
  extends PhabricatorChartDataset {

  const DATASETKEY = 'stacked-area';

  protected function newChartDisplayData(
    PhabricatorChartDataQuery $data_query) {
    $functions = $this->getFunctions();

    $reversed_functions = array_reverse($functions, true);

    $function_points = array();
    foreach ($reversed_functions as $function_idx => $function) {
      $function_points[$function_idx] = array();

      $datapoints = $function->newDatapoints($data_query);
      foreach ($datapoints as $point) {
        $x = $point['x'];
        $function_points[$function_idx][$x] = $point;
      }
    }

    $raw_points = $function_points;

    // We need to define every function we're drawing at every point where
    // any of the functions we're drawing are defined. If we don't, we'll
    // end up with weird gaps or overlaps between adjacent areas, and won't
    // know how much we need to lift each point above the baseline when
    // stacking the functions on top of one another.

    $must_define = array();
    foreach ($function_points as $function_idx => $points) {
      foreach ($points as $x => $point) {
        $must_define[$x] = $x;
      }
    }
    ksort($must_define);

    foreach ($reversed_functions as $function_idx => $function) {
      $missing = array();
      foreach ($must_define as $x) {
        if (!isset($function_points[$function_idx][$x])) {
          $missing[$x] = true;
        }
      }

      if (!$missing) {
        continue;
      }

      $points = $function_points[$function_idx];

      $values = array_keys($points);
      $cursor = -1;
      $length = count($values);

      foreach ($missing as $x => $ignored) {
        // Move the cursor forward until we find the last point before "x"
        // which is defined.
        while ($cursor + 1 < $length && $values[$cursor + 1] < $x) {
          $cursor++;
        }

        // If this new point is to the left of all defined points, we'll
        // assume the value is 0. If the point is to the right of all defined
        // points, we assume the value is the same as the last known value.

        // If it's between two defined points, we average them.

        if ($cursor < 0) {
          $y = 0;
        } else if ($cursor + 1 < $length) {
          $xmin = $values[$cursor];
          $xmax = $values[$cursor + 1];

          $ymin = $points[$xmin]['y'];
          $ymax = $points[$xmax]['y'];

          // Fill in the missing point by creating a linear interpolation
          // between the two adjacent points.
          $distance = ($x - $xmin) / ($xmax - $xmin);
          $y = $ymin + (($ymax - $ymin) * $distance);
        } else {
          $xmin = $values[$cursor];
          $y = $function_points[$function_idx][$xmin]['y'];
        }

        $function_points[$function_idx][$x] = array(
          'x' => $x,
          'y' => $y,
        );
      }

      ksort($function_points[$function_idx]);
    }

    $range_min = null;
    $range_max = null;

    $series = array();
    $baseline = array();
    foreach ($function_points as $function_idx => $points) {
      $below = idx($function_points, $function_idx - 1);

      $bounds = array();
      foreach ($points as $x => $point) {
        if (!isset($baseline[$x])) {
          $baseline[$x] = 0;
        }

        $y0 = $baseline[$x];
        $baseline[$x] += $point['y'];
        $y1 = $baseline[$x];

        $bounds[] = array(
          'x' => $x,
          'y0' => $y0,
          'y1' => $y1,
        );

        if (isset($raw_points[$function_idx][$x])) {
          $raw_points[$function_idx][$x]['y1'] = $y1;
        }

        if ($range_min === null) {
          $range_min = $y0;
        }
        $range_min = min($range_min, $y0, $y1);

        if ($range_max === null) {
          $range_max = $y1;
        }
        $range_max = max($range_max, $y0, $y1);
      }

      $series[] = $bounds;
    }

    $series = array_reverse($series);

    $events = array();
    foreach ($raw_points as $function_idx => $points) {
      $event_list = array();
      foreach ($points as $point) {
        $event_list[] = $point;
      }
      $events[] = $event_list;
    }

    $wire_labels = array();
    foreach ($functions as $function_key => $function) {
      $label = $function->getFunctionLabel();
      $wire_labels[] = $label->toWireFormat();
    }

    $result = array(
      'type' => $this->getDatasetTypeKey(),
      'data' => $series,
      'events' => $events,
      'labels' => $wire_labels,
    );

    return id(new PhabricatorChartDisplayData())
      ->setWireData($result)
      ->setRange(new PhabricatorChartInterval($range_min, $range_max));
  }


}
