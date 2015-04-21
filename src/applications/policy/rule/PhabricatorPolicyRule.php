<?php

abstract class PhabricatorPolicyRule {

  const CONTROL_TYPE_TEXT       = 'text';
  const CONTROL_TYPE_SELECT     = 'select';
  const CONTROL_TYPE_TOKENIZER  = 'tokenizer';
  const CONTROL_TYPE_NONE       = 'none';

  abstract public function getRuleDescription();
  abstract public function applyRule(PhabricatorUser $viewer, $value);

  public function willApplyRules(PhabricatorUser $viewer, array $values) {
    return;
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_TEXT;
  }

  public function getValueControlTemplate() {
    return null;
  }

  protected function getDatasourceTemplate(
    PhabricatorTypeaheadDatasource $datasource) {
    return array(
      'markup' => new AphrontTokenizerTemplateView(),
      'uri' => $datasource->getDatasourceURI(),
      'placeholder' => $datasource->getPlaceholderText(),
      'browseURI' => $datasource->getBrowseURI(),
    );
  }

  public function getRuleOrder() {
    return 500;
  }

  public function getValueForStorage($value) {
    return $value;
  }

  public function getValueForDisplay(PhabricatorUser $viewer, $value) {
    return $value;
  }

  public function getRequiredHandlePHIDsForSummary($value) {
    $phids = array();
    switch ($this->getValueControlType()) {
      case self::CONTROL_TYPE_TOKENIZER:
        $phids = $value;
        break;
      case self::CONTROL_TYPE_TEXT:
      case self::CONTROL_TYPE_SELECT:
      case self::CONTROL_TYPE_NONE:
      default:
        if (phid_get_type($value) !=
            PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN) {
          $phids = array($value);
        } else {
          $phids = array();
        }
        break;
    }

    return $phids;
  }

  /**
   * Return true if the given value creates a rule with a meaningful effect.
   * An example of a rule with no meaningful effect is a "users" rule with no
   * users specified.
   *
   * @return bool True if the value creates a meaningful rule.
   */
  public function ruleHasEffect($value) {
    return true;
  }

}
