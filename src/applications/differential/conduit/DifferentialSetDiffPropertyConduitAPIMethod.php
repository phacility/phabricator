<?php

final class DifferentialSetDiffPropertyConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.setdiffproperty';
  }

  public function getMethodDescription() {
    return pht('Attach properties to Differential diffs.');
  }

  protected function defineParamTypes() {
    return array(
      'diff_id' => 'required diff_id',
      'name'    => 'required string',
      'data'    => 'required string',
    );
  }

  protected function defineReturnType() {
    return 'void';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => pht('Diff was not found.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $data = $request->getValue('data');
    if ($data === null || !strlen($data)) {
      throw new Exception(pht('Field "data" must be non-empty.'));
    }

    $diff_id = $request->getValue('diff_id');
    if ($diff_id === null) {
      throw new Exception(pht('Field "diff_id" must be non-null.'));
    }

    $name = $request->getValue('name');
    if ($name === null || !strlen($name)) {
      throw new Exception(pht('Field "name" must be non-empty.'));
    }

    $data = json_decode($data, true);

    self::updateDiffProperty($diff_id, $name, $data);
  }

  private static function updateDiffProperty($diff_id, $name, $data) {
    $property = id(new DifferentialDiffProperty())->loadOneWhere(
      'diffID = %d AND name = %s',
      $diff_id,
      $name);
    if (!$property) {
      $property = new DifferentialDiffProperty();
      $property->setDiffID($diff_id);
      $property->setName($name);
    }
    $property->setData($data);
    $property->save();
    return $property;
  }

}
