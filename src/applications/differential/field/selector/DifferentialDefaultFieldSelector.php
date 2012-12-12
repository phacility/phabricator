<?php

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
      new DifferentialLintFieldSpecification(),
      new DifferentialUnitFieldSpecification(),
      new DifferentialCommitsFieldSpecification(),
      new DifferentialDependsOnFieldSpecification(),
      new DifferentialDependenciesFieldSpecification(),
      new DifferentialManiphestTasksFieldSpecification(),
      new DifferentialHostFieldSpecification(),
      new DifferentialPathFieldSpecification(),
      new DifferentialBranchFieldSpecification(),
      new DifferentialArcanistProjectFieldSpecification(),
      new DifferentialApplyPatchFieldSpecification(),
      new DifferentialRevisionIDFieldSpecification(),
      new DifferentialGitSVNIDFieldSpecification(),
      new DifferentialConflictsFieldSpecification(),
      new DifferentialDateModifiedFieldSpecification(),
      new DifferentialDateCreatedFieldSpecification(),
      new DifferentialAuditorsFieldSpecification(),
    );

    return $fields;
  }

  public function sortFieldsForRevisionList(array $fields) {
    assert_instances_of($fields, 'DifferentialFieldSpecification');

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

  public function sortFieldsForMail(array $fields) {
    assert_instances_of($fields, 'DifferentialFieldSpecification');

    $map = array();
    foreach ($fields as $field) {
      $map[get_class($field)] = $field;
    }

    $map = array_select_keys(
      $map,
      array(
        'DifferentialReviewersFieldSpecification',
        'DifferentialSummaryFieldSpecification',
        'DifferentialTestPlanFieldSpecification',
        'DifferentialRevisionIDFieldSpecification',
        'DifferentialManiphestTasksFieldSpecification',
        'DifferentialBranchFieldSpecification',
        'DifferentialArcanistProjectFieldSpecification',
        'DifferentialCommitsFieldSpecification',
      )) + $map;

    return array_values($map);
  }

}

