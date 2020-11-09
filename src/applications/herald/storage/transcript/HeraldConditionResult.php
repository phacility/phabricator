<?php

final class HeraldConditionResult
  extends Phobject {

  const RESULT_MATCHED = 'matched';
  const RESULT_FAILED = 'failed';
  const RESULT_OBJECT_STATE = 'object-state';
  const RESULT_INVALID = 'invalid';
  const RESULT_EXCEPTION = 'exception';
  const RESULT_UNKNOWN = 'unknown';

  private $resultCode;
  private $resultData = array();

  public function toMap() {
    return array(
      'code' => $this->getResultCode(),
      'data' => $this->getResultData(),
    );
  }

  public static function newFromMap(array $map) {
    $result_code = idx($map, 'code');
    $result = self::newFromResultCode($result_code);

    $result_data = idx($map, 'data', array());
    $result->setResultData($result_data);

    return $result;
  }

  public static function newFromResultCode($result_code) {
    $map = self::getResultSpecification($result_code);

    $result = new self();
    $result->resultCode = $result_code;

    return $result;
  }

  public function getResultCode() {
    return $this->resultCode;
  }

  private function getResultData() {
    return $this->resultData;
  }

  public function getIconIcon() {
    return $this->getSpecificationProperty('icon');
  }

  public function getIconColor() {
    return $this->getSpecificationProperty('color.icon');
  }

  public function getIsMatch() {
    return ($this->getSpecificationProperty('match') === true);
  }

  public function getName() {
    return $this->getSpecificationProperty('name');
  }

  public function newDetailsView() {
    switch ($this->resultCode) {
      case self::RESULT_OBJECT_STATE:
        $reason = $this->getDataProperty('reason');
        $details = HeraldStateReasons::getExplanation($reason);
        break;
      case self::RESULT_INVALID:
      case self::RESULT_EXCEPTION:
        $error_class = $this->getDataProperty('exception.class');
        $error_message = $this->getDataProperty('exception.message');

        if (!strlen($error_class)) {
          $error_class = pht('Unknown Error');
        }

        switch ($error_class) {
          case 'HeraldInvalidConditionException':
            $error_class = pht('Invalid Condition');
            break;
        }

        if (!strlen($error_message)) {
          $error_message = pht(
            'An unknown error occurred while evaluating this condition. No '.
            'additional information is available.');
        }

        $details = pht(
          '%s: %s',
          phutil_tag('strong', array(), $error_class),
          phutil_escape_html_newlines($error_message));
        break;
        $details = 'exception';
        break;
      default:
        $details = null;
        break;
    }

    return $details;
  }

  public function setResultData(array $result_data) {
    $this->resultData = $result_data;
    return $this;
  }

  private function getDataProperty($key) {
    $data = $this->getResultData();
    return idx($data, $key);
  }

  private function getSpecificationProperty($key) {
    $map = self::getResultSpecification($this->resultCode);
    return $map[$key];
  }

  private static function getResultSpecification($result_code) {
    $map = self::getResultSpecificationMap();

    if (!isset($map[$result_code])) {
      throw new Exception(
        pht(
          'Condition result "%s" is unknown.',
          $result_code));
    }

    return $map[$result_code];
  }

  private static function getResultSpecificationMap() {
    return array(
      self::RESULT_MATCHED => array(
        'match' => true,
        'icon' => 'fa-check',
        'color.icon' => 'green',
        'name' => pht('Passed'),
      ),
      self::RESULT_FAILED => array(
        'match' => false,
        'icon' => 'fa-times',
        'color.icon' => 'red',
        'name' => pht('Failed'),
      ),
      self::RESULT_OBJECT_STATE => array(
        'match' => null,
        'icon' => 'fa-ban',
        'color.icon' => 'indigo',
        'name' => pht('Forbidden'),
      ),
      self::RESULT_INVALID => array(
        'match' => null,
        'icon' => 'fa-exclamation-triangle',
        'color.icon' => 'yellow',
        'name' => pht('Invalid'),
      ),
      self::RESULT_EXCEPTION => array(
        'match' => null,
        'icon' => 'fa-exclamation-triangle',
        'color.icon' => 'red',
        'name' => pht('Exception'),
      ),
      self::RESULT_UNKNOWN => array(
        'match' => null,
        'icon' => 'fa-question',
        'color.icon' => 'grey',
        'name' => pht('Unknown'),
      ),
    );
  }

}
