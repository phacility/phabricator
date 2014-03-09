<?php

final class DifferentialLintFieldSpecification {

  public function renderWarningBoxForRevisionAccept() {
    $status = $this->getDiff()->getLintStatus();
    if ($status < DifferentialLintStatus::LINT_WARN) {
      return null;
    }

    $severity = AphrontErrorView::SEVERITY_ERROR;
    $titles = array(
      DifferentialLintStatus::LINT_WARN => 'Lint Warning',
      DifferentialLintStatus::LINT_FAIL => 'Lint Failure',
      DifferentialLintStatus::LINT_SKIP => 'Lint Skipped',
      DifferentialLintStatus::LINT_POSTPONED => 'Lint Postponed',
    );

    if ($status == DifferentialLintStatus::LINT_SKIP) {
      $content =
        "This diff was created without running lint. Make sure you are ".
        "OK with that before you accept this diff.";

    } else if ($status == DifferentialLintStatus::LINT_POSTPONED) {
      $severity = AphrontErrorView::SEVERITY_WARNING;
      $content =
        "Postponed linters didn't finish yet. Make sure you are OK with ".
        "that before you accept this diff.";

    } else {
      $content =
        "This diff has Lint Problems. Make sure you are OK with them ".
        "before you accept this diff.";
    }

    return id(new AphrontErrorView())
      ->setSeverity($severity)
      ->appendChild(phutil_tag('p', array(), $content))
      ->setTitle(idx($titles, $status, 'Warning'));
  }

}
