<?php

final class AphrontFormDateControlValue extends Phobject {

  private $valueDay;
  private $valueMonth;
  private $valueYear;
  private $valueTime;

  private $viewer;
  private $zone;

  public function getValueDay() {
    return $this->valueDay;
  }

  public function getValueMonth() {
    return $this->valueMonth;
  }

  public function getValueYear() {
    return $this->valueYear;
  }

  public function getValueTime() {
    return $this->valueTime;
  }

  public function isValid() {
    return ($this->getEpoch() !== null);
  }

  public static function newFromParts(
    PhabricatorUser $viewer,
    $year,
    $month,
    $day,
    $time = '12:00 AM') {

    $value = new AphrontFormDateControlValue();
    $value->viewer = $viewer;
    $value->valueYear = $year;
    $value->valueMonth = $month;
    $value->valueDay = $day;
    $value->valueTime = $time;

    return $value;
  }

  public static function newFromRequest($request, $key) {
    $value = new AphrontFormDateControlValue();
    $value->viewer = $request->getViewer();

    $value->valueDay = $request->getInt($key.'_d');
    $value->valueMonth = $request->getInt($key.'_m');
    $value->valueYear = $request->getInt($key.'_y');
    $value->valueTime = $request->getStr($key.'_t');

    return $value;
  }

  public static function newFromEpoch(PhabricatorUser $viewer, $epoch) {
    $value = new AphrontFormDateControlValue();
    $value->viewer = $viewer;
    $readable = $value->formatTime($epoch, 'Y!m!d!g:i A');
    $readable = explode('!', $readable, 4);

    $value->valueYear  = $readable[0];
    $value->valueMonth = $readable[1];
    $value->valueDay   = $readable[2];
    $value->valueTime  = $readable[3];

    return $value;
  }

  private function formatTime($epoch, $format) {
    return phabricator_format_local_time(
      $epoch,
      $this->viewer,
      $format);
  }

  public function getEpoch() {
    $year = $this->valueYear;
    $month = $this->valueMonth;
    $day = $this->valueDay;
    $time = $this->valueTime;
    $zone = $this->getTimezone();

    if (!strlen($time)) {
      return null;
    }

    try {
      $date = new DateTime("{$year}-{$month}-{$day} {$time}", $zone);
      $value = $date->format('U');
    } catch (Exception $ex) {
      $value = null;
    }
    return $value;
  }

  private function getTimezone() {
    if ($this->zone) {
      return $this->zone;
    }

    $viewer_zone = $this->viewer->getTimezoneIdentifier();
    $this->zone = new DateTimeZone($viewer_zone);
    return $this->zone;
  }


}
