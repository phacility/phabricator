<?php

abstract class PhabricatorAuthFactor extends Phobject {

  abstract public function getFactorName();
  abstract public function getFactorKey();
  abstract public function getFactorDescription();
  abstract public function processAddFactorForm(
    AphrontFormView $form,
    AphrontRequest $request,
    PhabricatorUser $user);

  public static function getAllFactors() {
    static $factors;

    if ($factors === null) {
      $map = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      $factors = array();
      foreach ($map as $factor) {
        $key = $factor->getFactorKey();
        if (empty($factors[$key])) {
          $factors[$key] = $factor;
        } else {
          $this_class = get_class($factor);
          $that_class = get_class($factors[$key]);

          throw new Exception(
            pht(
              'Two auth factors (with classes "%s" and "%s") both provide '.
              'implementations with the same key ("%s"). Each factor must '.
              'have a unique key.',
              $this_class,
              $that_class,
              $key));
        }
      }
    }

    return $factors;
  }

  protected function newConfigForUser(PhabricatorUser $user) {
    return id(new PhabricatorAuthFactorConfig())
      ->setUserPHID($user->getPHID())
      ->setFactorKey($this->getFactorKey());
  }

}
