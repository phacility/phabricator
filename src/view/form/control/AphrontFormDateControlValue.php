<?php

final class AphrontFormDateControlValue extends Phobject {

  private $valueDay;
  private $valueMonth;
  private $valueYear;
  private $valueTime;
  private $valueEnabled;

  private $viewer;
  private $zone;
  private $optional;

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
    if ($this->isDisabled()) {
      return true;
    }
    return ($this->getEpoch() !== null);
  }

  public function isEmpty() {
    if ($this->valueDay) {
      return false;
    }

    if ($this->valueMonth) {
      return false;
    }

    if ($this->valueYear) {
      return false;
    }

    if ($this->valueTime) {
      return false;
    }

    return true;
  }

  public function isDisabled() {
    return ($this->optional && !$this->valueEnabled);
  }

  public function setEnabled($enabled) {
    $this->valueEnabled = $enabled;
    return $this;
  }

  public function setOptional($optional) {
    $this->optional = $optional;
    return $this;
  }

  public function getOptional() {
    return $this->optional;
  }

  public static function newFromParts(
    PhabricatorUser $viewer,
    $year,
    $month,
    $day,
    $time = null,
    $enabled = true) {

    $value = new AphrontFormDateControlValue();
    $value->viewer = $viewer;
    $value->valueYear = $year;
    $value->valueMonth = $month;
    $value->valueDay = $day;
    $value->valueTime = coalesce($time, '12:00 AM');
    $value->valueEnabled = $enabled;

    return $value;
  }

  public static function newFromRequest(AphrontRequest $request, $key) {
    $value = new AphrontFormDateControlValue();
    $value->viewer = $request->getViewer();

    $value->valueDay = $request->getInt($key.'_d');
    $value->valueMonth = $request->getInt($key.'_m');
    $value->valueYear = $request->getInt($key.'_y');
    $value->valueTime = $request->getStr($key.'_t');
    $value->valueEnabled = $request->getStr($key.'_e');

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

  public static function newFromDictionary(
    PhabricatorUser $viewer,
    array $dictionary) {
    $value = new AphrontFormDateControlValue();
    $value->viewer = $viewer;

    $value->valueYear = idx($dictionary, 'y');
    $value->valueMonth = idx($dictionary, 'm');
    $value->valueDay = idx($dictionary, 'd');
    $value->valueTime = idx($dictionary, 't');
    $value->valueEnabled = idx($dictionary, 'e');

    return $value;
  }

  public static function newFromWild(PhabricatorUser $viewer, $wild) {
    if (is_array($wild)) {
      return self::newFromDictionary($viewer, $wild);
    } else if (is_numeric($wild)) {
      return self::newFromEpoch($viewer, $wild);
    } else {
      throw new Exception(
        pht(
          'Unable to construct a date value from value of type "%s".',
          gettype($wild)));
    }
  }

  public function getDictionary() {
    return array(
      'y' => $this->valueYear,
      'm' => $this->valueMonth,
      'd' => $this->valueDay,
      't' => $this->valueTime,
      'e' => $this->valueEnabled,
    );
  }

  private function formatTime($epoch, $format) {
    return phabricator_format_local_time(
      $epoch,
      $this->viewer,
      $format);
  }

  public function getEpoch() {
    if ($this->isDisabled()) {
      return null;
    }

    $year = $this->valueYear;
    $month = $this->valueMonth;
    $day = $this->valueDay;
    $time = $this->valueTime;
    $zone = $this->getTimezone();

    if (!strlen($time)) {
      return null;
    }

    $colloquial = array(
      'elevenses' => '11:00 AM',
      'morning tea' => '11:00 AM',
      'noon' => '12:00 PM',
      'high noon' => '12:00 PM',
      'lunch' => '12:00 PM',
      'tea time' => '3:00 PM',
      'witching hour' => '12:00 AM',
      'midnight' => '12:00 AM',
    );

    $normalized = phutil_utf8_strtolower($time);
    if (isset($colloquial[$normalized])) {
      $time = $colloquial[$normalized];
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
