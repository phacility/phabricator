<?php

final class HeraldRuleResult
  extends HeraldTranscriptResult {

  const RESULT_ANY_MATCHED = 'any-match';
  const RESULT_ALL_MATCHED = 'all-match';
  const RESULT_ANY_FAILED = 'any-failed';
  const RESULT_ALL_FAILED = 'all-failed';
  const RESULT_LAST_MATCHED = 'last-match';
  const RESULT_VERSION = 'version';
  const RESULT_EMPTY = 'empty';
  const RESULT_OWNER = 'owner';
  const RESULT_VIEW_POLICY = 'view-policy';
  const RESULT_OBJECT_RULE = 'object-rule';
  const RESULT_EXCEPTION = 'exception';
  const RESULT_EVALUATION_EXCEPTION = 'evaluation-exception';
  const RESULT_UNKNOWN = 'unknown';
  const RESULT_ALREADY_APPLIED = 'already-applied';
  const RESULT_OBJECT_STATE = 'object-state';
  const RESULT_RECURSION = 'recursion';

  public static function newFromResultCode($result_code) {
    return id(new self())->setResultCode($result_code);
  }

  public static function newFromResultMap(array $map) {
    return id(new self())->loadFromResultMap($map);
  }

  public function getShouldRecordMatch() {
    return ($this->getSpecificationProperty('match') === true);
  }

  public function getShouldApplyActions() {
    return ($this->getSpecificationProperty('apply') === true);
  }

  public function getDescription() {
    return $this->getSpecificationProperty('description');
  }

  public function newDetailsView(PhabricatorUser $viewer) {
    switch ($this->getResultCode()) {
      case self::RESULT_EXCEPTION:
        $error_class = $this->getDataProperty('exception.class');
        $error_message = $this->getDataProperty('exception.message');

        if (!strlen($error_class)) {
          $error_class = pht('Unknown Error');
        }

        if (!strlen($error_message)) {
          $error_message = pht(
            'An unknown error occurred while evaluating this condition. No '.
            'additional information is available.');
        }

        $details = $this->newErrorView($error_class, $error_message);
        break;
      case self::RESULT_RECURSION:
        $rule_phids = $this->getDataProperty('cyclePHIDs', array());
        $handles = $viewer->loadHandles($rule_phids);

        $links = array();
        foreach ($rule_phids as $rule_phid) {
          $links[] = $handles[$rule_phid]->renderLink();
        }

        $links = phutil_implode_html(' > ', $links);

        $details = array(
          pht('This rule has a dependency cycle and can not be evaluated:'),
          ' ',
          $links,
        );
        break;
      default:
        $details = null;
        break;
    }

    return $details;
  }

  protected function newResultSpecificationMap() {
    return array(
      self::RESULT_ANY_MATCHED => array(
        'match' => true,
        'apply' => true,
        'name' => pht('Matched'),
        'description' => pht('Any condition matched.'),
        'icon' => 'fa-check-circle',
        'color.icon' => 'green',
      ),
      self::RESULT_ALL_MATCHED => array(
        'match' => true,
        'apply' => true,
        'name' => pht('Matched'),
        'description' => pht('All conditions matched.'),
        'icon' => 'fa-check-circle',
        'color.icon' => 'green',
      ),
      self::RESULT_ANY_FAILED => array(
        'match' => false,
        'apply' => false,
        'name' => pht('Failed'),
        'description' => pht('Not all conditions matched.'),
        'icon' => 'fa-times-circle',
        'color.icon' => 'red',
      ),
      self::RESULT_ALL_FAILED => array(
        'match' => false,
        'apply' => false,
        'name' => pht('Failed'),
        'description' => pht('No conditions matched.'),
        'icon' => 'fa-times-circle',
        'color.icon' => 'red',
      ),
      self::RESULT_LAST_MATCHED => array(
        'match' => true,
        'apply' => false,
        'name' => pht('Failed'),
        'description' => pht(
          'This rule matched, but did not take any actions because it '.
          'is configured to act only if it did not match the last time.'),
        'icon' => 'fa-times-circle',
        'color.icon' => 'red',
      ),
      self::RESULT_VERSION => array(
        'match' => null,
        'apply' => false,
        'name' => pht('Version Issue'),
        'description' => pht(
          'Rule could not be processed because it was created with a newer '.
          'version of Herald.'),
        'icon' => 'fa-times-circle',
        'color.icon' => 'red',
      ),
      self::RESULT_EMPTY => array(
        'match' => null,
        'apply' => false,
        'name' => pht('Empty'),
        'description' => pht(
          'Rule failed automatically because it has no conditions.'),
        'icon' => 'fa-times-circle',
        'color.icon' => 'red',
      ),
      self::RESULT_OWNER => array(
        'match' => null,
        'apply' => false,
        'name' => pht('Rule Owner'),
        'description' => pht(
          'Rule failed automatically because it is a personal rule and '.
          'its owner is invalid or disabled.'),
        'icon' => 'fa-times-circle',
        'color.icon' => 'red',
      ),
      self::RESULT_VIEW_POLICY => array(
        'match' => null,
        'apply' => false,
        'name' => pht('View Policy'),
        'description' => pht(
          'Rule failed automatically because it is a personal rule and '.
          'its owner does not have permission to view the object.'),
        'icon' => 'fa-times-circle',
        'color.icon' => 'red',
      ),
      self::RESULT_OBJECT_RULE => array(
        'match' => null,
        'apply' => false,
        'name' => pht('Object Rule'),
        'description' => pht(
          'Rule failed automatically because it is an object rule which is '.
          'not relevant for this object.'),
        'icon' => 'fa-times-circle',
        'color.icon' => 'red',
      ),
      self::RESULT_EXCEPTION => array(
        'match' => null,
        'apply' => false,
        'name' => pht('Exception'),
        'description' => pht(
          'Rule failed because an exception occurred.'),
        'icon' => 'fa-times-circle',
        'color.icon' => 'red',
      ),
      self::RESULT_EVALUATION_EXCEPTION => array(
        'match' => null,
        'apply' => false,
        'name' => pht('Exception'),
        'description' => pht(
          'Rule failed because an exception occurred while evaluating it.'),
        'icon' => 'fa-times-circle',
        'color.icon' => 'red',
      ),
      self::RESULT_UNKNOWN => array(
        'match' => null,
        'apply' => false,
        'name' => pht('Unknown'),
        'description' => pht(
          'Rule evaluation result is unknown.'),
        'icon' => 'fa-times-circle',
        'color.icon' => 'red',
      ),
      self::RESULT_ALREADY_APPLIED => array(
        'match' => null,
        'apply' => false,
        'name' => pht('Already Applied'),
        'description' => pht(
          'This rule is only supposed to be repeated a single time, '.
          'and it has already been applied.'),
        'icon' => 'fa-times-circle',
        'color.icon' => 'red',
      ),
      self::RESULT_OBJECT_STATE => array(
        'match' => null,
        'apply' => false,
        'name' => pht('Forbidden'),
        'description' => pht(
          'Object state prevented rule evaluation.'),
        'icon' => 'fa-ban',
        'color.icon' => 'indigo',
      ),
      self::RESULT_RECURSION => array(
        'match' => null,
        'apply' => false,
        'name' => pht('Recursion'),
        'description' => pht(
          'This rule has a recursive dependency on itself and can not '.
          'be evaluated.'),
        'icon' => 'fa-times-circle',
        'color.icon' => 'red',
      ),
    );
  }

}
