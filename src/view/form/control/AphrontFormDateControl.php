<?php

final class AphrontFormDateControl extends AphrontFormControl {

  private $user;
  private $initialTime;

  private $valueDay;
  private $valueMonth;
  private $valueYear;
  private $valueTime;

  const TIME_START_OF_DAY         = 'start-of-day';
  const TIME_END_OF_DAY           = 'end-of-day';
  const TIME_START_OF_BUSINESS    = 'start-of-business';
  const TIME_END_OF_BUSINESS      = 'end-of-business';

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setInitialTime($time) {
    $this->initialTime = $time;
    return $this;
  }

  public function readValueFromRequest(AphrontRequest $request) {
    $user = $this->user;
    if (!$this->user) {
      throw new Exception(
        "Call setUser() before readValueFromRequest()!");
    }

    $user_zone = $user->getTimezoneIdentifier();
    $zone = new DateTimeZone($user_zone);

    $day = $request->getInt($this->getDayInputName());
    $month = $request->getInt($this->getMonthInputName());
    $year = $request->getInt($this->getYearInputName());
    $time = $request->getStr($this->getTimeInputName());

    $err = $this->getError();

    if ($day || $month || $year || $time) {
      $this->valueDay = $day;
      $this->valueMonth = $month;
      $this->valueYear = $year;
      $this->valueTime = $time;

      // Assume invalid.
      $err = 'Invalid';

      try {
        $date = new DateTime("{$year}-{$month}-{$day} {$time}", $zone);
        $value = $date->format('U');
      } catch (Exception $ex) {
        $value = null;
      }

      if ($value) {
        $this->setValue($value);
        $err = null;
      } else {
        $this->setValue(null);
      }
    } else {
      // TODO: We could eventually allow these to be customized per install or
      // per user or both, but let's wait and see.
      switch ($this->initialTime) {
        case self::TIME_START_OF_DAY:
        default:
          $time = '12:00 AM';
          break;
        case self::TIME_START_OF_BUSINESS:
          $time = '9:00 AM';
          break;
        case self::TIME_END_OF_BUSINESS:
          $time = '5:00 PM';
          break;
        case self::TIME_END_OF_DAY:
          $time = '11:59 PM';
          break;
      }

      $today = $this->formatTime(time(), 'Y-m-d');
      try {
        $date = new DateTime("{$today} {$time}", $zone);
        $value = $date->format('U');
      } catch (Exception $ex) {
        $value = null;
      }

      if ($value) {
        $this->setValue($value);
      } else {
        $this->setValue(null);
      }
    }

    $this->setError($err);

    return $this->getValue();
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-date';
  }

  public function setValue($epoch) {
    $result = parent::setValue($epoch);

    if ($epoch === null) {
      return;
    }

    $readable = $this->formatTime($epoch, 'Y!m!d!g:i A');
    $readable = explode('!', $readable, 4);

    $this->valueYear  = $readable[0];
    $this->valueMonth = $readable[1];
    $this->valueDay   = $readable[2];
    $this->valueTime  = $readable[3];

    return $result;
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
    return $this->valueDay;
  }

  private function getMonthInputValue() {
    return $this->valueMonth;
  }

  private function getYearInputValue() {
    return $this->valueYear;
  }

  private function getTimeInputValue() {
    return $this->valueTime;
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

  private function getTimeInputName() {
    return $this->getName().'_t';
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

    $time_sel = phutil_render_tag(
      'input',
      array(
        'name'  => $this->getTimeInputName(),
        'sigil' => 'time-input',
        'value' => $this->getTimeInputValue(),
        'type'  => 'text',
        'class' => 'aphront-form-date-time-input',
      ),
      '');

    Javelin::initBehavior('fancy-datepicker', array());

    return javelin_render_tag(
      'div',
      array(
        'class' => 'aphront-form-date-container',
        'sigil' => 'phabricator-date-control',
      ),
      self::renderSingleView(
        array(
          $days_sel,
          $months_sel,
          $years_sel,
          $cal_icon,
          $time_sel,
        )));
  }

}
