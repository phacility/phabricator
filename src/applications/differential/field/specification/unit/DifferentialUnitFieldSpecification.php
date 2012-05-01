<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class DifferentialUnitFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Unit:';
  }

  public function getRequiredDiffProperties() {
    return array('arc:unit', 'arc:unit-excuse');
  }

  private function getUnitExcuse() {
    return $this->getDiffProperty('arc:unit-excuse');
  }

  public function renderValueForRevisionView() {
    $diff = $this->getDiff();

    $ustar = DifferentialRevisionUpdateHistoryView::renderDiffUnitStar($diff);
    $umsg = DifferentialRevisionUpdateHistoryView::getDiffUnitMessage($diff);

    $rows = array();

    $rows[] = array(
      'style' => 'star',
      'name'  => $ustar,
      'value' => $umsg,
      'show'  => true,
    );

    $excuse = $this->getUnitExcuse();
    if ($excuse) {
      $rows[] = array(
        'style' => 'excuse',
        'name'  => 'Excuse',
        'value' => nl2br(phutil_escape_html($excuse)),
        'show'  => true,
      );
    }

    $show_limit = 10;
    $hidden = array();

    $udata = $this->getDiffProperty('arc:unit');
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

      foreach ($udata as $test) {
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

        $rows[] = array(
          'style' => $this->getResultStyle($result),
          'name'  => phutil_escape_html(ucwords($result)),
          'value' => phutil_escape_html(idx($test, 'name')),
          'show'  => $show,
        );

        $userdata = idx($test, 'userdata');
        if ($userdata) {
          $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();
          $userdata = $engine->markupText($userdata);
          $rows[] = array(
            'style' => 'details',
            'value' => $userdata,
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
      ArcanistUnitTestResult::RESULT_BROKEN     => 'Broken',
      ArcanistUnitTestResult::RESULT_FAIL       => 'Failed',
      ArcanistUnitTestResult::RESULT_UNSOUND    => 'Unsound',
      ArcanistUnitTestResult::RESULT_SKIP       => 'Skipped',
      ArcanistUnitTestResult::RESULT_POSTPONED  => 'Postponed',
      ArcanistUnitTestResult::RESULT_PASS       => 'Passed',
      'details'                                 => 'Details',
    );

    $show = array();
    foreach ($hidden as $key => $value) {
      $show[] = $value.' '.idx($noun, $key);
    }

    return "Show Full Unit Results (".implode(', ', $show).")";
  }

}
