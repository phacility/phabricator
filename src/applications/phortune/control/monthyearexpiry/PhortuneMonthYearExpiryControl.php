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

final class PhortuneMonthYearExpiryControl extends AphrontFormControl {
  private $user;
  private $monthValue;
  private $yearValue;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }
  private function getUser() {
    return $this->user;
  }

  public function setMonthInputValue($value) {
    $this->monthValue = $value;
    return $this;
  }
  private function getMonthInputValue() {
    return $this->monthValue;
  }
  private function getCurrentMonth() {
    return phabricator_format_local_time(
      time(),
      $this->getUser(),
      'm');
  }

  public function setYearInputValue($value) {
    $this->yearValue = $value;
    return $this;
  }
  private function getYearInputValue() {
    return $this->yearValue;
  }
  private function getCurrentYear() {
    return phabricator_format_local_time(
      time(),
      $this->getUser(),
      'Y');
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-text';
  }

  protected function renderInput() {
    if (!$this->getUser()) {
      throw new Exception('You must setUser() before render()!');
    }

    // represent months like a credit card does
    $months = array(
      '01' => '01',
      '02' => '02',
      '03' => '03',
      '04' => '04',
      '05' => '05',
      '06' => '06',
      '07' => '07',
      '08' => '08',
      '09' => '09',
      '10' => '10',
      '11' => '11',
      '12' => '12',
    );

    $current_year = $this->getCurrentYear();
    $years = range($current_year, $current_year + 20);
    $years = array_combine($years, $years);

    if ($this->getMonthInputValue()) {
      $selected_month = $this->getMonthInputValue();
    } else {
      $selected_month = $this->getCurrentMonth();
    }
    $months_sel = AphrontFormSelectControl::renderSelectTag(
      $selected_month,
      $months,
      array(
        'sigil' => 'month-input',
      ));

    $years_sel = AphrontFormSelectControl::renderSelectTag(
      $this->getYearInputValue(),
      $years,
      array(
        'sigil' => 'year-input',
      ));

    return self::renderSingleView(
      array(
        $months_sel,
        $years_sel
      )
    );
  }

}
