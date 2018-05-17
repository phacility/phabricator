<?php

final class PhabricatorOwnersPackageIgnoredTransaction
  extends PhabricatorOwnersPackageTransactionType {

  const TRANSACTIONTYPE = 'owners.ignored';

  public function generateOldValue($object) {
    return $object->getIgnoredPathAttributes();
  }

  public function generateNewValue($object, $value) {
    return array_fill_keys($value, true);
  }

  public function applyInternalEffects($object, $value) {
    $object->setIgnoredPathAttributes($value);
  }

  public function getTitle() {
    $old = array_keys($this->getOldValue());
    $new = array_keys($this->getNewValue());

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    $all_n = new PhutilNumber(count($add) + count($rem));
    $add_n = phutil_count($add);
    $rem_n = phutil_count($rem);

    if ($new && $old) {
      return pht(
        '%s changed %s ignored attribute(s), added %s: %s; removed %s: %s.',
        $this->renderAuthor(),
        $all_n,
        $add_n,
        $this->renderValueList($add),
        $rem_n,
        $this->renderValueList($rem));
    } else if ($new) {
      return pht(
        '%s changed %s ignored attribute(s), added %s: %s.',
        $this->renderAuthor(),
        $all_n,
        $add_n,
        $this->rendervalueList($add));
    } else {
      return pht(
        '%s changed %s ignored attribute(s), removed %s: %s.',
        $this->renderAuthor(),
        $all_n,
        $rem_n,
        $this->rendervalueList($rem));
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $valid_attributes = array(
      'generated' => true,
    );

    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      foreach ($new as $attribute) {
        if (isset($valid_attributes[$attribute])) {
          continue;
        }

        $errors[] = $this->newInvalidError(
          pht(
            'Changeset attribute "%s" is not valid. Valid changeset '.
            'attributes are: %s.',
            $attribute,
            implode(', ', array_keys($valid_attributes))),
          $xaction);
      }
    }

    return $errors;
  }

}
