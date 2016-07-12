<?php

final class PhortuneMonthYearExpiryControl extends AphrontFormControl {
  private $monthValue;
  private $yearValue;

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
      throw new PhutilInvalidStateException('setUser');
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
    $years = array_fuse($years);

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

    return hsprintf('%s%s', $months_sel, $years_sel);
  }

}
