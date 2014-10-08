<?php

final class DifferentialGetRawDiffConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.getrawdiff';
  }

  public function getMethodDescription() {
    return pht('Retrieve a raw diff');
  }

  public function defineParamTypes() {
    return array(
      'diffID' => 'required diffID',
    );
  }

  public function defineReturnType() {
    return 'nonempty string';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => pht('Diff not found.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $diff_id = $request->getValue('diffID');

    $viewer = $request->getUser();

    $diff = id(new DifferentialDiffQuery())
      ->withIDs(array($diff_id))
      ->setViewer($viewer)
      ->needChangesets(true)
      ->executeOne();

    if (!$diff) {
      throw new ConduitException('ERR_NOT_FOUND');
    }

    $renderer = id(new DifferentialRawDiffRenderer())
      ->setChangesets($diff->getChangesets())
      ->setViewer($viewer)
      ->setFormat('git');

    return $renderer->buildPatch();
  }

}
