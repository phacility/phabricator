<?php

abstract class PhabricatorAuthFactor extends Phobject {

  abstract public function getFactorName();
  abstract public function getFactorKey();
  abstract public function getFactorDescription();
  abstract public function processAddFactorForm(
    AphrontFormView $form,
    AphrontRequest $request,
    PhabricatorUser $user);

  abstract public function renderValidateFactorForm(
    PhabricatorAuthFactorConfig $config,
    AphrontFormView $form,
    PhabricatorUser $viewer,
    $validation_result);

  abstract public function processValidateFactorForm(
    PhabricatorAuthFactorConfig $config,
    PhabricatorUser $viewer,
    AphrontRequest $request);

  public function isFactorValid(
    PhabricatorAuthFactorConfig $config,
    $validation_result) {
    return (idx($validation_result, 'valid') === true);
  }

  public function getParameterName(
    PhabricatorAuthFactorConfig $config,
    $name) {
    return 'authfactor.'.$config->getID().'.'.$name;
  }

  public static function getAllFactors() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getFactorKey')
      ->execute();
  }

  protected function newConfigForUser(PhabricatorUser $user) {
    return id(new PhabricatorAuthFactorConfig())
      ->setUserPHID($user->getPHID())
      ->setFactorKey($this->getFactorKey());
  }

}
