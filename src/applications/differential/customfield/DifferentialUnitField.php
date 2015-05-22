<?php

final class DifferentialUnitField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:unit';
  }

  public function getFieldName() {
    return pht('Unit');
  }

  public function getFieldDescription() {
    return pht('Shows unit test results.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function getRequiredDiffPropertiesForRevisionView() {
    return array(
      'arc:unit',
      'arc:unit-excuse',
    );
  }

  public function renderPropertyViewValue(array $handles) {
    $diff = $this->getObject()->getActiveDiff();

    $ustar = DifferentialRevisionUpdateHistoryView::renderDiffUnitStar($diff);
    $umsg = DifferentialRevisionUpdateHistoryView::getDiffUnitMessage($diff);

    $rows = array();

    $rows[] = array(
      'style' => 'star',
      'name'  => $ustar,
      'value' => $umsg,
      'show'  => true,
    );

    $excuse = $diff->getProperty('arc:unit-excuse');
    if ($excuse) {
      $rows[] = array(
        'style' => 'excuse',
        'name'  => pht('Excuse'),
        'value' => phutil_escape_html_newlines($excuse),
        'show'  => true,
      );
    }

    $show_limit = 10;
    $hidden = array();

    $udata = $diff->getProperty('arc:unit');
    if ($udata) {
      $sort_map = array(
        ArcanistUnitTestResult::RESULT_BROKEN     => 0,
        ArcanistUnitTestResult::RESULT_FAIL       => 1,
        ArcanistUnitTestResult::RESULT_UNSOUND    => 2,
        ArcanistUnitTestResult::RESULT_SKIP       => 3,
        ArcanistUnitTestResult::RESULT_POSTPONED  => 4,
        ArcanistUnitTestResult::RESULT_PASS       => 5,
      );

      foreach ($udata as $key => $test) {
        $udata[$key]['sort'] = idx($sort_map, idx($test, 'result'));
      }
      $udata = isort($udata, 'sort');
      $engine = new PhabricatorMarkupEngine();
      $engine->setViewer($this->getViewer());
      $markup_objects = array();
      foreach ($udata as $key => $test) {
        $userdata = idx($test, 'userdata');
        if ($userdata) {
          if ($userdata !== false) {
            $userdata = str_replace("\000", '', $userdata);
          }
          $markup_object = id(new PhabricatorMarkupOneOff())
            ->setContent($userdata)
            ->setPreserveLinebreaks(true);
          $engine->addObject($markup_object, 'default');
          $markup_objects[$key] = $markup_object;
        }
      }
      $engine->process();
      foreach ($udata as $key => $test) {
        $result = idx($test, 'result');

        $default_hide = false;
        switch ($result) {
          case ArcanistUnitTestResult::RESULT_POSTPONED:
          case ArcanistUnitTestResult::RESULT_PASS:
            $default_hide = true;
            break;
        }

        if ($show_limit && !$default_hide) {
          --$show_limit;
          $show = true;
        } else {
          $show = false;
          if (empty($hidden[$result])) {
            $hidden[$result] = 0;
          }
          $hidden[$result]++;
        }

        $value = idx($test, 'name');

        $namespace = idx($test, 'namespace');
        if ($namespace) {
          $value = $namespace.'::'.$value;
        }

        if (!empty($test['link'])) {
          $value = phutil_tag(
            'a',
            array(
              'href' => $test['link'],
              'target' => '_blank',
            ),
            $value);
        }
        $rows[] = array(
          'style' => $this->getResultStyle($result),
          'name'  => ucwords($result),
          'value' => $value,
          'show'  => $show,
        );

        if (isset($markup_objects[$key])) {
          $rows[] = array(
            'style' => 'details',
            'value' => $engine->getOutput($markup_objects[$key], 'default'),
            'show'  => false,
          );
          if (empty($hidden['details'])) {
            $hidden['details'] = 0;
          }
          $hidden['details']++;
        }
      }
    }

    $show_string = $this->renderShowString($hidden);

    $view = new DifferentialResultsTableView();
    $view->setRows($rows);
    $view->setShowMoreString($show_string);

    return $view->render();
  }

  private function getResultStyle($result) {
    $map = array(
      ArcanistUnitTestResult::RESULT_PASS       => 'green',
      ArcanistUnitTestResult::RESULT_FAIL       => 'red',
      ArcanistUnitTestResult::RESULT_SKIP       => 'blue',
      ArcanistUnitTestResult::RESULT_BROKEN     => 'red',
      ArcanistUnitTestResult::RESULT_UNSOUND    => 'yellow',
      ArcanistUnitTestResult::RESULT_POSTPONED  => 'blue',
    );
    return idx($map, $result);
  }

  private function renderShowString(array $hidden) {
    if (!$hidden) {
      return null;
    }

    // Reorder hidden things by severity.
    $hidden = array_select_keys(
      $hidden,
      array(
        ArcanistUnitTestResult::RESULT_BROKEN,
        ArcanistUnitTestResult::RESULT_FAIL,
        ArcanistUnitTestResult::RESULT_UNSOUND,
        ArcanistUnitTestResult::RESULT_SKIP,
        ArcanistUnitTestResult::RESULT_POSTPONED,
        ArcanistUnitTestResult::RESULT_PASS,
        'details',
      )) + $hidden;

    $noun = array(
      ArcanistUnitTestResult::RESULT_BROKEN     => pht('Broken'),
      ArcanistUnitTestResult::RESULT_FAIL       => pht('Failed'),
      ArcanistUnitTestResult::RESULT_UNSOUND    => pht('Unsound'),
      ArcanistUnitTestResult::RESULT_SKIP       => pht('Skipped'),
      ArcanistUnitTestResult::RESULT_POSTPONED  => pht('Postponed'),
      ArcanistUnitTestResult::RESULT_PASS       => pht('Passed'),
    );

    $show = array();
    foreach ($hidden as $key => $value) {
      if ($key == 'details') {
        $show[] = pht('%d Detail(s)', $value);
      } else {
        $show[] = $value.' '.idx($noun, $key);
      }
    }

    return pht(
      'Show Full Unit Results (%s)',
      implode(', ', $show));
  }

  public function getWarningsForDetailView() {
    $status = $this->getObject()->getActiveDiff()->getUnitStatus();

    $warnings = array();
    if ($status < DifferentialUnitStatus::UNIT_WARN) {
      // Don't show any warnings.
    } else if ($status == DifferentialUnitStatus::UNIT_AUTO_SKIP) {
      // Don't show any warnings.
    } else if ($status == DifferentialUnitStatus::UNIT_POSTPONED) {
      $warnings[] = pht(
        'Background tests have not finished executing on these changes.');
    } else if ($status == DifferentialUnitStatus::UNIT_SKIP) {
      $warnings[] = pht(
        'Unit tests were skipped when generating these changes.');
    } else {
      $warnings[] = pht('These changes have unit test problems.');
    }

    return $warnings;
  }


}
