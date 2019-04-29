<?php

final class PhabricatorChartDataset
  extends Phobject {

  private $function;

  public function getFunction() {
    return $this->function;
  }

  public function setFunction(PhabricatorComposeChartFunction $function) {
    $this->function = $function;
    return $this;
  }

  public static function newFromDictionary(array $map) {
    PhutilTypeSpec::checkMap(
      $map,
      array(
        'function' => 'list<wild>',
      ));

    $dataset = new self();

    $dataset->function = id(new PhabricatorComposeChartFunction())
      ->setArguments(array($map['function']));

    return $dataset;
  }

  public function toDictionary() {
    // Since we wrap the raw value in a "compose(...)", when deserializing,
    // we need to unwrap it when serializing.
    $function_raw = head($this->getFunction()->toDictionary());

    return array(
      'function' => $function_raw,
    );
  }

}
