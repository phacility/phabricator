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

final class AphrontFormDateControl extends AphrontFormControl {

  private $user;

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function readValueFromRequest(AphrontRequest $request) {

    $day = $request->getInt($this->getDayInputName());
    $month = $request->getInt($this->getMonthInputName());
    $year = $request->getInt($this->getYearInputName());

    $err = $this->getError();

    if ($day || $month || $year) {

      // Assume invalid.
      $err = 'Invalid';

      $tz = new DateTimeZone('UTC');
      try {
        $date = new DateTime("{$year}-{$month}-{$day} 12:00:00 AM", $tz);
        $value = $date->format('Y-m-d');
        if ($value) {
          $this->setValue($value);
          $err = null;
        }
      } catch (Exception $ex) {
        // Ignore, already handled.
      }
    }

    $this->setError($err);

    return $err;
  }

  public function getValue() {
    if (!parent::getValue()) {
      $this->setValue($this->formatTime(time(), 'Y-m-d'));
    }
    return parent::getValue();
  }


  protected function getCustomControlClass() {
    return 'aphront-form-control-date';
  }

  private function getMinYear() {
    $cur_year = $this->formatTime(
      time(),
      'Y');
    $val_year = $this->getYearInputValue();

    return min($cur_year, $val_year) - 3;
  }

  private function getMaxYear() {
    $cur_year = $this->formatTime(
      time(),
      'Y');
    $val_year = $this->getYearInputValue();

    return max($cur_year, $val_year) + 3;
  }

  private function getDayInputValue() {
    return (int)idx(explode('-', $this->getValue()), 2);
  }

  private function getMonthInputValue() {
    return (int)idx(explode('-', $this->getValue()), 1);
  }

  private function getYearInputValue() {
    return (int)idx(explode('-', $this->getValue()), 0);
  }

  private function formatTime($epoch, $fmt) {
    return phabricator_format_local_time(
      $epoch,
      $this->user,
      $fmt);
  }

  private function getDayInputName() {
    return $this->getName().'_d';
  }

  private function getMonthInputName() {
    return $this->getName().'_m';
  }

  private function getYearInputName() {
    return $this->getName().'_y';
  }

  protected function renderInput() {
    $min_year = $this->getMinYear();
    $max_year = $this->getMaxYear();

    $days = range(1, 31);
    $days = array_combine($days, $days);

    $months = array(
      1 => 'Jan',
      2 => 'Feb',
      3 => 'Mar',
      4 => 'Apr',
      5 => 'May',
      6 => 'Jun',
      7 => 'Jul',
      8 => 'Aug',
      9 => 'Sep',
      10 => 'Oct',
      11 => 'Nov',
      12 => 'Dec',
    );

    $years = range($this->getMinYear(), $this->getMaxYear());
    $years = array_combine($years, $years);

    $days_sel = AphrontFormSelectControl::renderSelectTag(
      $this->getDayInputValue(),
      $days,
      array(
        'name' => $this->getDayInputName(),
        'sigil' => 'day-input',
      ));

    $months_sel = AphrontFormSelectControl::renderSelectTag(
      $this->getMonthInputValue(),
      $months,
      array(
        'name' => $this->getMonthInputName(),
        'sigil' => 'month-input',
      ));

    $years_sel = AphrontFormSelectControl::renderSelectTag(
      $this->getYearInputValue(),
      $years,
      array(
        'name'  => $this->getYearInputName(),
        'sigil' => 'year-input',
      ));

    $cal_icon = javelin_render_tag(
      'a',
      array(
        'href'  => '#',
        'class' => 'calendar-button',
        'sigil' => 'calendar-button',
      ),
      '');

    $id = celerity_generate_unique_node_id();

    Javelin::initBehavior(
      'fancy-datepicker',
      array(
        'root' => $id,
      ));

    return javelin_render_tag(
      'div',
      array(
        'id' => $id,
        'class' => 'aphront-form-date-container',
      ),
      self::renderSingleView(
        array(
          $days_sel,
          $months_sel,
          $years_sel,
          $cal_icon,
        )));
  }

}
