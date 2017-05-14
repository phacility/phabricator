<?php

final class PholioImageNameTransaction
  extends PholioImageTransactionType {

  const TRANSACTIONTYPE = 'image-name';

  public function generateOldValue($object) {
    $name = null;
    $phid = null;
    $image = $this->getImageForXaction($object);
    if ($image) {
      $name = $image->getName();
      $phid = $image->getPHID();
    }
    return array($phid => $name);
  }

  public function applyInternalEffects($object, $value) {
    $image = $this->getImageForXaction($object);
    $value = (string)head($this->getNewValue());
    $image->setName($value);
    $image->save();
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    return pht(
      '%s renamed an image (%s) from %s to %s.',
      $this->renderAuthor(),
      $this->renderHandle(key($new)),
      $this->renderValue($old),
      $this->renderValue($new));
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the image names of %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function mergeTransactions(
    $object,
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $raw_new_value_u = $u->getNewValue();
    $raw_new_value_v = $v->getNewValue();
    $phid_u = head_key($raw_new_value_u);
    $phid_v = head_key($raw_new_value_v);
    if ($phid_u == $phid_v) {
      return $v;
    }

    return null;
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    return ($old === array(null => null));
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $max_length = $object->getColumnMaximumByteLength('name');
    foreach ($xactions as $xaction) {
      $new_value = head(array_values($xaction->getNewValue()));
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht(
            'Mock image names must not be longer than %s character(s).',
            new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }


}
