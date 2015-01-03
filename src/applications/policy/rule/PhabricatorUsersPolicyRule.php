<?php

final class PhabricatorUsersPolicyRule extends PhabricatorPolicyRule {

  public function getRuleDescription() {
    return pht('users');
  }

  public function applyRule(PhabricatorUser $viewer, $value) {
    foreach ($value as $phid) {
      if ($phid == $viewer->getPHID()) {
        return true;
      }
    }
    return false;
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_TOKENIZER;
  }

  public function getValueControlTemplate() {
    $users_datasource = new PhabricatorPeopleDatasource();

    return array(
      'markup' => new AphrontTokenizerTemplateView(),
      'uri' => $users_datasource->getDatasourceURI(),
      'placeholder' => $users_datasource->getPlaceholderText(),
    );
  }

  public function getRuleOrder() {
    return 100;
  }

  public function getValueForStorage($value) {
    PhutilTypeSpec::newFromString('list<string>')->check($value);
    return array_values($value);
  }

  public function getValueForDisplay(PhabricatorUser $viewer, $value) {
    if (!$value) {
      return array();
    }

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($value)
      ->execute();

    return mpull($handles, 'getFullName', 'getPHID');
  }

  public function ruleHasEffect($value) {
    return (bool)$value;
  }

}
