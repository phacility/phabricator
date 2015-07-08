<?php

final class AphrontFormDateControlValue extends Phobject {

  private $valueDate;
  private $valueTime;
  private $valueEnabled;

  private $viewer;
  private $zone;
  private $optional;

  public function getValueDate() {
    return $this->valueDate;
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
    if ($this->valueDate) {
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

  public function getViewer() {
    return $this->viewer;
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
    list($value->valueDate, $value->valueTime) =
      $value->getFormattedDateFromParts(
        $year,
        $month,
        $day,
        coalesce($time, '12:00 AM'));
    $value->valueEnabled = $enabled;

    return $value;
  }

  public static function newFromRequest(AphrontRequest $request, $key) {
    $value = new AphrontFormDateControlValue();
    $value->viewer = $request->getViewer();

    list($value->valueDate, $value->valueTime) =
      $value->getFormattedDateFromDate(
        $request->getStr($key.'_d'),
        $request->getStr($key.'_t'));

    $value->valueEnabled = $request->getStr($key.'_e');
    return $value;
  }

  public static function newFromEpoch(PhabricatorUser $viewer, $epoch) {
    $value = new AphrontFormDateControlValue();
    $value->viewer = $viewer;
    $readable = $value->formatTime($epoch, 'Y!m!d!g:i A');
    $readable = explode('!', $readable, 4);

    $year  = $readable[0];
    $month = $readable[1];
    $day   = $readable[2];
    $time  = $readable[3];

    list($value->valueDate, $value->valueTime) =
      $value->getFormattedDateFromParts(
        $year,
        $month,
        $day,
        $time);

    return $value;
  }

  public static function newFromDictionary(
    PhabricatorUser $viewer,
    array $dictionary) {
    $value = new AphrontFormDateControlValue();
    $value->viewer = $viewer;

    list($value->valueDate, $value->valueTime) =
      $value->getFormattedDateFromDate(
        idx($dictionary, 'd'),
        idx($dictionary, 't'));

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
      'd' => $this->valueDate,
      't' => $this->valueTime,
      'e' => $this->valueEnabled,
    );
  }

  public function getValueAsFormat($format) {
    return phabricator_format_local_time(
      $this->getEpoch(),
      $this->viewer,
      $format);
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

    $date = $this->valueDate;
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
      $datetime = new DateTime("{$date} {$time}", $zone);
      $value = $datetime->format('U');
    } catch (Exception $ex) {
      $value = null;
    }
    return $value;
  }

  private function getTimeFormat() {
    return $this->getViewer()
      ->getPreference(PhabricatorUserPreferences::PREFERENCE_TIME_FORMAT);
  }

  private function getDateFormat() {
    return $this->getViewer()
      ->getPreference(PhabricatorUserPreferences::PREFERENCE_DATE_FORMAT);
  }

  private function getFormattedDateFromDate($date, $time) {
    $original_input = $date;
    $zone = $this->getTimezone();
    $separator = $this->getFormatSeparator();
    $parts = preg_split('@[,./:-]@', $date);
    $date = implode($separator, $parts);
    $date = id(new DateTime($date, $zone));

    if ($date) {
      $date = $date->format($this->getDateFormat());
    } else {
      $date = $original_input;
    }

    $date = id(new DateTime("{$date} {$time}", $zone));

    return array(
      $date->format($this->getDateFormat()),
      $date->format($this->getTimeFormat()),
    );
  }

  private function getFormattedDateFromParts(
    $year,
    $month,
    $day,
    $time) {

    $zone = $this->getTimezone();
    $date_time = id(new DateTime("{$year}-{$month}-{$day} {$time}", $zone));

    return array(
      $date_time->format($this->getDateFormat()),
      $date_time->format($this->getTimeFormat()),
    );
  }

  private function getFormatSeparator() {
    $format = $this->getDateFormat();
    switch ($format) {
      case 'n/j/Y':
        return '/';
      default:
        return '-';
    }
  }

  public function getDateTime() {
    $epoch = $this->getEpoch();
    $date = null;

    if ($epoch) {
      $zone = $this->getTimezone();
      $date = new DateTime('@'.$epoch);
      $date->setTimeZone($zone);
    }

    return $date;
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
