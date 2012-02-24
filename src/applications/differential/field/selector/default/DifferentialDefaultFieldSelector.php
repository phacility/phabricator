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

final class DifferentialDefaultFieldSelector
  extends DifferentialFieldSelector {

  public function getFieldSpecifications() {
    $fields = array(
      new DifferentialTitleFieldSpecification(),
      new DifferentialSummaryFieldSpecification(),
      new DifferentialTestPlanFieldSpecification(),
      new DifferentialRevisionStatusFieldSpecification(),
      new DifferentialAuthorFieldSpecification(),
      new DifferentialReviewersFieldSpecification(),
      new DifferentialReviewedByFieldSpecification(),
      new DifferentialCCsFieldSpecification(),
      new DifferentialUnitFieldSpecification(),
      new DifferentialLintFieldSpecification(),
      new DifferentialCommitsFieldSpecification(),
      new DifferentialDependenciesFieldSpecification(),
      new DifferentialManiphestTasksFieldSpecification(),
    );

    if (PhabricatorEnv::getEnvConfig('differential.show-host-field')) {
      $fields = array_merge(
        $fields,
        array(
          new DifferentialHostFieldSpecification(),
          new DifferentialPathFieldSpecification(),
        ));
    }

    $fields = array_merge(
      $fields,
      array(
        new DifferentialBranchFieldSpecification(),
        new DifferentialArcanistProjectFieldSpecification(),
        new DifferentialApplyPatchFieldSpecification(),
        new DifferentialRevisionIDFieldSpecification(),
        new DifferentialGitSVNIDFieldSpecification(),
        new DifferentialDateModifiedFieldSpecification(),
        new DifferentialDateCreatedFieldSpecification(),
      ));

    return $fields;
  }

  public function sortFieldsForRevisionList(array $fields) {
    $map = array();
    foreach ($fields as $field) {
      $map[get_class($field)] = $field;
    }

    $map = array_select_keys(
      $map,
      array(
        'DifferentialRevisionIDFieldSpecification',
        'DifferentialTitleFieldSpecification',
        'DifferentialRevisionStatusFieldSpecification',
        'DifferentialAuthorFieldSpecification',
        'DifferentialReviewersFieldSpecification',
        'DifferentialDateModifiedFieldSpecification',
        'DifferentialDateCreatedFieldSpecification',
      )) + $map;

    return array_values($map);
  }

}

