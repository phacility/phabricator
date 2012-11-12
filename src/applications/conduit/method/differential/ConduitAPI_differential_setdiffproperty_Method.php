<?php

/**
 * @group conduit
 */
final class ConduitAPI_differential_setdiffproperty_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Attach properties to Differential diffs.";
  }

  public function defineParamTypes() {
    return array(
      'diff_id' => 'required diff_id',
      'name'    => 'required string',
      'data'    => 'required string',
    );
  }

  public function defineReturnType() {
    return 'void';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => 'Diff was not found.',
    );
  }

  private static function updateLintStatus($diff_id) {

    $diff = id(new DifferentialDiff())->load($diff_id);
    if (!$diff) {
      throw new ConduitException('ERR_NOT_FOUND');
    }

    // Load the postponed linters attached to this diff.
    $postponed_linters_property = id(
      new DifferentialDiffProperty())->loadOneWhere(
        'diffID = %d AND name = %s',
        $diff_id,
        'arc:lint-postponed');
    if ($postponed_linters_property) {
      $postponed_linters = $postponed_linters_property->getData();
    } else {
      $postponed_linters = array();
    }

    // Load the lint messages currenty attached to the diff
    $messages_property = id(new DifferentialDiffProperty())->loadOneWhere(
      'diffID = %d AND name = %s',
      $diff_id,
      'arc:lint'
    );
    if ($messages_property) {
      $results = $messages_property->getData();
    } else {
      $results = array();
    }

    $has_error = false;
    $has_warning = false;
    foreach ($results as $result) {
      if ($result['severity'] === ArcanistLintSeverity::SEVERITY_ERROR) {
        $has_error = true;
        break;
      } else if ($result['severity'] ===
                 ArcanistLintSeverity::SEVERITY_WARNING) {
        $has_warning = true;
      }
    }

    if ($has_error) {
      $diff->setLintStatus(DifferentialLintStatus::LINT_FAIL);
    } else if ($has_warning) {
      $diff->setLintStatus(DifferentialLintStatus::LINT_WARN);
    } else if (!empty($postponed_linters)) {
      $diff->setLintStatus(DifferentialLintStatus::LINT_POSTPONED);
    } else if ($results &&
               $diff->getLintStatus() === DifferentialLintStatus::LINT_NONE) {
      $diff->setLintStatus(DifferentialLintStatus::LINT_OKAY);
    }
    $diff->save();
  }

  protected function execute(ConduitAPIRequest $request) {
    $diff_id = $request->getValue('diff_id');
    $name = $request->getValue('name');
    $data = json_decode($request->getValue('data'), true);

    self::updateDiffProperty($diff_id, $name, $data);

    if ($name === 'arc:lint' || $name == 'arc:lint-postponed') {
      self::updateLintStatus($diff_id);
    }

    return;
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
