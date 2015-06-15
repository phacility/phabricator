<?php

final class DifferentialLintStatus extends Phobject {

  const LINT_NONE             = 0;
  const LINT_OKAY             = 1;
  const LINT_WARN             = 2;
  const LINT_FAIL             = 3;
  const LINT_SKIP             = 4;
  const LINT_POSTPONED        = 5;
  const LINT_AUTO_SKIP        = 6;

}
