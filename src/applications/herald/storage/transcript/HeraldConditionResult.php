<?php

final class HeraldConditionResult
  extends HeraldTranscriptResult {

  const RESULT_MATCHED = 'matched';
  const RESULT_FAILED = 'failed';
  const RESULT_OBJECT_STATE = 'object-state';
  const RESULT_INVALID = 'invalid';
  const RESULT_RECURSION = 'recursion';
  const RESULT_EXCEPTION = 'exception';
  const RESULT_UNKNOWN = 'unknown';

  public static function newFromResultCode($result_code) {
    return id(new self())->setResultCode($result_code);
  }

  public static function newFromResultMap(array $map) {
    return id(new self())->loadFromResultMap($map);
  }

  public function getIsMatch() {
    return ($this->getSpecificationProperty('match') === true);
  }

  public function newDetailsView(PhabricatorUser $viewer) {
    switch ($this->getResultCode()) {
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
      default:
        $details = null;
        break;
    }

    return $details;
  }

  protected function newResultSpecificationMap() {
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
      self::RESULT_RECURSION => array(
        'match' => null,
        'icon' => 'fa-exclamation-triangle',
        'color.icon' => 'red',
        'name' => pht('Recursion'),
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
