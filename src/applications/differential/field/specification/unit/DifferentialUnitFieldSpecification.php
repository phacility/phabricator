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
    $excuse = $this->getDiffProperty('arc:unit-excuse');
    $excuse = phutil_escape_html($excuse);
    $excuse = nl2br($excuse);

    $excuse_markup = '';
    if (strlen($excuse)) {
      $excuse_markup = '<p>Explanation for failure(s): </p>'.
                       '<span class="unit-excuse">'.$excuse.'</span>';
    }
    return $excuse_markup;
  }

  public function renderValueForRevisionView() {
    $diff = $this->getDiff();

    $ustar = DifferentialRevisionUpdateHistoryView::renderDiffUnitStar($diff);
    $umsg = DifferentialRevisionUpdateHistoryView::getDiffUnitMessage($diff);

    $postponed_count = 0;
    $udata = $this->getDiffProperty('arc:unit');
    $utail = null;

    if ($udata) {
      $unit_messages = array();
      foreach ($udata as $test) {
        $name = idx($test, 'name');
        $result = idx($test, 'result');

        if ($result != DifferentialUnitTestResult::RESULT_POSTPONED &&
            $result != DifferentialUnitTestResult::RESULT_PASS) {
          $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();
          $userdata = phutil_utf8_shorten(idx($test, 'userdata'), 512);
          $userdata = $engine->markupText($userdata);

          $unit_messages[] =
            '<li>'.
              '<span class="unit-result-'.phutil_escape_html($result).'">'.
                phutil_escape_html(ucwords($result)).
              '</span>'.
              ' '.
              phutil_escape_html($name).
              '<p>'.$userdata.'</p>'.
            '</li>';

        } else if ($result == DifferentialUnitTestResult::RESULT_POSTPONED) {
          $postponed_count++;
        }
      }

      $uexcuse = $this->getUnitExcuse();
      if ($unit_messages) {
        $utail =
          '<div class="differential-unit-block">'.
            $uexcuse.
            '<ul>'.
              implode("\n", $unit_messages).
            '</ul>'.
          '</div>';
      }
    }

    if ($postponed_count > 0 &&
        $diff->getUnitStatus() == DifferentialUnitStatus::UNIT_POSTPONED) {
      $umsg = $postponed_count.' '.$umsg;
    }

    return $ustar.' '.$umsg.$utail;
  }

}
