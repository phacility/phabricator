<?php

final class DifferentialUpdateUnitResultsConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.updateunitresults';
  }

  public function getMethodDescription() {
    return pht('Update arc unit results for a postponed test.');
  }

  protected function defineParamTypes() {
    return array(
      'diff_id'   => 'required diff_id',
      'file'      => 'required string',
      'name'      => 'required string',
      'link'      => 'optional string',
      'result'    => 'required string',
      'message'   => 'required string',
      'coverage'  => 'optional map<string, string>',
    );
  }

  protected function defineReturnType() {
    return 'void';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_BAD_DIFF'   => pht('Bad diff ID.'),
      'ERR_NO_RESULTS' => pht('Could not find the postponed test'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $diff_id = $request->getValue('diff_id');
    if (!$diff_id) {
      throw new ConduitException('ERR_BAD_DIFF');
    }

    $file = $request->getValue('file');
    $name = $request->getValue('name');
    $link = $request->getValue('link');
    $message = $request->getValue('message');
    $result = $request->getValue('result');
    $coverage = $request->getValue('coverage', array());

    $diff_property = id(new DifferentialDiffProperty())->loadOneWhere(
      'diffID = %d AND name = %s',
      $diff_id,
      'arc:unit');

    if (!$diff_property) {
      throw new ConduitException('ERR_NO_RESULTS');
    }

    $diff = id(new DifferentialDiffQuery())
      ->setViewer($request->getUser())
      ->withIDs(array($diff_id))
      ->executeOne();

    $unit_results = $diff_property->getData();
    $postponed_count = 0;
    $unit_status = null;

    // If the test result already exists, then update it with
    // the new info.
    foreach ($unit_results as &$unit_result) {
      if ($unit_result['name'] === $name ||
          $unit_result['name'] === $file ||
          $unit_result['name'] === $diff->getSourcePath().$file) {
        $unit_result['name'] = $name;
        $unit_result['link'] = $link;
        $unit_result['file'] = $file;
        $unit_result['result'] = $result;
        $unit_result['userdata'] = $message;
        $unit_result['coverage'] = $coverage;
        $unit_status = $result;
        break;
      }
    }
    unset($unit_result);

    // If the test result doesn't exist, just add it.
    if (!$unit_status) {
      $unit_result = array();
      $unit_result['file'] = $file;
      $unit_result['name'] = $name;
      $unit_result['link'] = $link;
      $unit_result['result'] = $result;
      $unit_result['userdata'] = $message;
      $unit_result['coverage'] = $coverage;
      $unit_status = $result;
      $unit_results[] = $unit_result;
    }
    unset($unit_result);

    $diff_property->setData($unit_results);
    $diff_property->save();

    // Map external unit test status to internal overall diff status
    $status_codes =
      array(
        DifferentialUnitTestResult::RESULT_PASS =>
          DifferentialUnitStatus::UNIT_OKAY,
        DifferentialUnitTestResult::RESULT_UNSOUND =>
          DifferentialUnitStatus::UNIT_WARN,
        DifferentialUnitTestResult::RESULT_FAIL =>
          DifferentialUnitStatus::UNIT_FAIL,
        DifferentialUnitTestResult::RESULT_BROKEN =>
          DifferentialUnitStatus::UNIT_FAIL,
        DifferentialUnitTestResult::RESULT_SKIP =>
          DifferentialUnitStatus::UNIT_OKAY,
        DifferentialUnitTestResult::RESULT_POSTPONED =>
          DifferentialUnitStatus::UNIT_POSTPONED,
      );

    // These are the relative priorities for the unit test results
    $status_codes_priority =
      array(
        DifferentialUnitStatus::UNIT_OKAY => 1,
        DifferentialUnitStatus::UNIT_WARN => 2,
        DifferentialUnitStatus::UNIT_POSTPONED => 3,
        DifferentialUnitStatus::UNIT_FAIL => 4,
      );

    // Walk the now-current list of status codes to find the overall diff
    // status
    $final_diff_status = DifferentialUnitStatus::UNIT_NONE;
    foreach ($unit_results as $unit_result) {
      // Convert the text result into a diff unit status value
      $status_code = idx($status_codes,
                         $unit_result['result'],
                         DifferentialUnitStatus::UNIT_NONE);

      // Convert the unit status into a relative value
      $diff_status_priority = idx($status_codes_priority, $status_code, 0);

      // If the relative value of this result is "more bad" than previous
      // results, use it as the new final diff status
      if ($diff_status_priority > idx($status_codes_priority,
                                      $final_diff_status, 0)) {
        $final_diff_status = $status_code;
      }
    }

    // Update our unit test result status with the final value
    $diff->setUnitStatus($final_diff_status);
    $diff->save();
  }

}
