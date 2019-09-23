<?php

final class PhabricatorChartStackedAreaDataset
  extends PhabricatorChartDataset {

  const DATASETKEY = 'stacked-area';

  private $stacks;

  public function setStacks(array $stacks) {
    $this->stacks = $stacks;
    return $this;
  }

  public function getStacks() {
    return $this->stacks;
  }

  protected function newChartDisplayData(
    PhabricatorChartDataQuery $data_query) {

    $functions = $this->getFunctions();
    $functions = mpull($functions, null, 'getKey');

    $stacks = $this->getStacks();

    if (!$stacks) {
      $stacks = array(
        array_reverse(array_keys($functions), true),
      );
    }

    $series = array();
    $raw_points = array();

    foreach ($stacks as $stack) {
      $stack_functions = array_select_keys($functions, $stack);

      $function_points = $this->getFunctionDatapoints(
        $data_query,
        $stack_functions);

      $stack_points = $function_points;

      $function_points = $this->getGeometry(
        $data_query,
        $function_points);

      $baseline = array();
      foreach ($function_points as $function_idx => $points) {
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

          if (isset($stack_points[$function_idx][$x])) {
            $stack_points[$function_idx][$x]['y1'] = $y1;
          }
        }

        $series[$function_idx] = $bounds;
      }

      $raw_points += $stack_points;
    }

    $series = array_select_keys($series, array_keys($functions));
    $series = array_values($series);

    $raw_points = array_select_keys($raw_points, array_keys($functions));
    $raw_points = array_values($raw_points);

    $range_min = null;
    $range_max = null;

    foreach ($series as $geometry_list) {
      foreach ($geometry_list as $geometry_item) {
        $y0 = $geometry_item['y0'];
        $y1 = $geometry_item['y1'];

        if ($range_min === null) {
          $range_min = $y0;
        }
        $range_min = min($range_min, $y0, $y1);

        if ($range_max === null) {
          $range_max = $y1;
        }
        $range_max = max($range_max, $y0, $y1);
      }
    }

    // We're going to group multiple events into a single point if they have
    // X values that are very close to one another.
    //
    // If the Y values are also close to one another (these points are near
    // one another in a horizontal line), it can be hard to select any
    // individual point with the mouse.
    //
    // Even if the Y values are not close together (the points are on a
    // fairly steep slope up or down), it's usually better to be able to
    // mouse over a single point at the top or bottom of the slope and get
    // a summary of what's going on.

    $domain_max = $data_query->getMaximumValue();
    $domain_min = $data_query->getMinimumValue();
    $resolution = ($domain_max - $domain_min) / 100;

    $events = array();
    foreach ($raw_points as $function_idx => $points) {
      $event_list = array();

      $event_group = array();
      $head_event = null;
      foreach ($points as $point) {
        $x = $point['x'];

        if ($head_event === null) {
          // We don't have any points yet, so start a new group.
          $head_event = $x;
          $event_group[] = $point;
        } else if (($x - $head_event) <= $resolution) {
          // This point is close to the first point in this group, so
          // add it to the existing group.
          $event_group[] = $point;
        } else {
          // This point is not close to the first point in the group,
          // so create a new group.
          $event_list[] = $event_group;
          $head_event = $x;
          $event_group = array($point);
        }
      }

      if ($event_group) {
        $event_list[] = $event_group;
      }

      $event_spec = array();
      foreach ($event_list as $key => $event_points) {
        // NOTE: We're using the last point as the representative point so
        // that you can learn about a section of a chart by hovering over
        // the point to right of the section, which is more intuitive than
        // other points.
        $event = last($event_points);

        $event = $event + array(
          'n' => count($event_points),
        );

        $event_list[$key] = $event;
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

  private function getAllXValuesAsMap(
    PhabricatorChartDataQuery $data_query,
    array $point_lists) {

    // We need to define every function we're drawing at every point where
    // any of the functions we're drawing are defined. If we don't, we'll
    // end up with weird gaps or overlaps between adjacent areas, and won't
    // know how much we need to lift each point above the baseline when
    // stacking the functions on top of one another.

    $must_define = array();

    $min = $data_query->getMinimumValue();
    $max = $data_query->getMaximumValue();
    $must_define[$max] = $max;
    $must_define[$min] = $min;

    foreach ($point_lists as $point_list) {
      foreach ($point_list as $x => $point) {
        $must_define[$x] = $x;
      }
    }

    ksort($must_define);

    return $must_define;
  }

  private function getFunctionDatapoints(
    PhabricatorChartDataQuery $data_query,
    array $functions) {

    assert_instances_of($functions, 'PhabricatorChartFunction');

    $points = array();
    foreach ($functions as $idx => $function) {
      $points[$idx] = array();

      $datapoints = $function->newDatapoints($data_query);
      foreach ($datapoints as $point) {
        $x_value = $point['x'];
        $points[$idx][$x_value] = $point;
      }
    }

    return $points;
  }

  private function getGeometry(
    PhabricatorChartDataQuery $data_query,
    array $point_lists) {

    $must_define = $this->getAllXValuesAsMap($data_query, $point_lists);

    foreach ($point_lists as $idx => $points) {

      $missing = array();
      foreach ($must_define as $x) {
        if (!isset($points[$x])) {
          $missing[$x] = true;
        }
      }

      if (!$missing) {
        continue;
      }

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
          $y = $points[$xmin]['y'];
        }

        $point_lists[$idx][$x] = array(
          'x' => $x,
          'y' => $y,
        );
      }

      ksort($point_lists[$idx]);
    }

    return $point_lists;
  }

}
