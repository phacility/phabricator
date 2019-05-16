<?php

final class PhabricatorRepositoryFetchRefsTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'fetch-refs';

  public function generateOldValue($object) {
    return $object->getFetchRules();
  }

  public function applyInternalEffects($object, $value) {
    $object->setFetchRules($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (!$new) {
      return pht(
        '%s set this repository to fetch all refs.',
        $this->renderAuthor());
    } else if (!$old) {
      return pht(
        '%s set this repository to fetch refs: %s.',
        $this->renderAuthor(),
        $this->renderValue(implode(', ', $new)));
    } else {
      return pht(
        '%s changed fetched refs from %s to %s.',
        $this->renderAuthor(),
        $this->renderValue(implode(', ', $old)),
        $this->renderValue(implode(', ', $new)));
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();

      if (!is_array($new_value) || !phutil_is_natural_list($new_value)) {
        $errors[] = $this->newInvalidError(
          pht(
            'Fetch rules must be a list of strings, got "%s".',
            phutil_describe_type($new_value)),
          $xaction);
        continue;
      }

      foreach ($new_value as $idx => $rule) {
        if (!is_string($rule)) {
          $errors[] = $this->newInvalidError(
            pht(
              'Fetch rule (at index "%s") must be a string, got "%s".',
              $idx,
              phutil_describe_type($rule)),
            $xaction);
          continue;
        }

        if (!strlen($rule)) {
          $errors[] = $this->newInvalidError(
            pht(
              'Fetch rule (at index "%s") is empty. Fetch rules must '.
              'contain text.',
              $idx),
            $xaction);
          continue;
        }

        // Since we fetch ref "X" as "+X:X", don't allow rules to include
        // colons. This is specific to Git and may not be relevant if
        // Mercurial repositories eventually get fetch rules.
        if (preg_match('/:/', $rule)) {
          $errors[] = $this->newInvalidError(
            pht(
              'Fetch rule ("%s", at index "%s") is invalid: fetch rules '.
              'must not contain colons.',
              $rule,
              $idx),
            $xaction);
          continue;
        }

      }
    }

    return $errors;
  }

}
