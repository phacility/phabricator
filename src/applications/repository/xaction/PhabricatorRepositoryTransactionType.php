<?php

abstract class PhabricatorRepositoryTransactionType
  extends PhabricatorModularTransactionType {

  protected function validateRefList($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      foreach ($xaction->getNewValue() as $pattern) {
        // Check for invalid regular expressions.
        $regexp = PhabricatorRepository::extractBranchRegexp($pattern);
        if ($regexp !== null) {
          $ok = @preg_match($regexp, '');
          if ($ok === false) {
            $errors[] = $this->newInvalidError(
              pht(
                'Expression "%s" is not a valid regular expression. Note '.
                'that you must include delimiters.',
                $regexp),
              $xaction);
            continue;
          }
        }

        // Check for formatting mistakes like `regex(...)` instead of
        // `regexp(...)`.
        $matches = null;
        if (preg_match('/^([^(]+)\\(.*\\)\z/', $pattern, $matches)) {
          switch ($matches[1]) {
            case 'regexp':
              break;
            default:
              $errors[] = $this->newInvalidError(
                pht(
                  'Matching function "%s(...)" is not recognized. Valid '.
                  'functions are: regexp(...).',
                  $matches[1]),
                $xaction);
              break;
          }
        }
      }
    }

    return $errors;
  }

}
