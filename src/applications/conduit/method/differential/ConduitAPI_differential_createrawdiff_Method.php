<?php

/**
 * @group conduit
 */
final class ConduitAPI_differential_createrawdiff_Method
  extends ConduitAPI_differential_Method {

  public function getMethodDescription() {
    return "Create a new Differential diff from a raw diff source.";
  }

  public function defineParamTypes() {
    return array(
      'diff' => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $raw_diff = $request->getValue('diff');

    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($raw_diff);
    $diff = DifferentialDiff::newFromRawChanges($changes);

    $diff->setLintStatus(DifferentialLintStatus::LINT_SKIP);
    $diff->setUnitStatus(DifferentialUnitStatus::UNIT_SKIP);

    $diff->setAuthorPHID($request->getUser()->getPHID());
    $diff->setCreationMethod('web');
    $diff->save();

    return $this->buildDiffInfoDictionary($diff);
  }

}
