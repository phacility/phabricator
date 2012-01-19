<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group conduit
 */
class ConduitAPI_differential_updateunitresults_Method
extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Update arc unit results for a postponed test.";
  }

  public function defineParamTypes() {
    return array(
      'diff_id' => 'required diff_id',
      'file'    => 'required string',
      'name'    => 'required string',
      'result'  => 'required string',
      'message' => 'required string',
    );
  }

  public function defineReturnType() {
    return 'void';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_DIFF'   => 'Bad diff ID.',
      'ERR_NO_RESULTS' => 'Could not find the postponed test',
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $diff_id = $request->getValue('diff_id');
    if (!$diff_id) {
      throw new ConduitException('ERR_BAD_DIFF');
    }

    $file = $request->getValue('file');
    $name = $request->getValue('name');
    $message = $request->getValue('message');
    $result = $request->getValue('result');

    $diff_property = id(new DifferentialDiffProperty())->loadOneWhere(
      'diffID = %d AND name = %s',
      $diff_id,
      'arc:unit'
      );

    if (!$diff_property) {
      throw new ConduitException('ERR_NO_RESULTS');
    }

    $diff = id(new DifferentialDiff())->load($diff_id);
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
        $unit_result['file'] = $file;
        $unit_result['result'] = $result;
        $unit_result['userdata'] = $message;
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
      $unit_result['result'] = $result;
      $unit_result['userdata'] = $message;
      $unit_status = $result;
      $unit_results[] = $unit_result;
    }

    $diff_property->setData($unit_results);
    $diff_property->save();

    foreach ($unit_results as $unit_result) {
      if ($unit_result['result'] ==
          DifferentialUnitTestResult::RESULT_POSTPONED) {
        $postponed_count++;
      }
    }

    $status_codes =
      array(
        DifferentialUnitTestResult::RESULT_PASS =>
          DifferentialUnitStatus::UNIT_OKAY,
        DifferentialUnitTestResult::RESULT_UNSOUND =>
          DifferentialUnitStatus::UNIT_WARN,
        DifferentialUnitTestResult::RESULT_FAIL =>
          DifferentialUnitStatus::UNIT_FAIL,
        DifferentialUnitTestResult::RESULT_SKIP =>
          DifferentialUnitStatus::UNIT_SKIP,
        DifferentialUnitTestResult::RESULT_POSTPONED =>
          DifferentialUnitStatus::UNIT_POSTPONED);

    if ($diff->getUnitStatus() == DifferentialUnitStatus::UNIT_POSTPONED) {
      if ($postponed_count == 0 ||
          $unit_status != DifferentialUnitTestResult::RESULT_PASS) {
        $diff->setUnitStatus(
          idx($status_codes, $unit_status, DifferentialUnitStatus::UNIT_NONE));
        $diff->save();
      }
    }

    return;
  }

}
