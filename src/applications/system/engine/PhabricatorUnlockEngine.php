<?php

abstract class PhabricatorUnlockEngine
  extends Phobject {

  final public static function newUnlockEngineForObject($object) {
    if (!($object instanceof PhabricatorApplicationTransactionInterface)) {
      throw new Exception(
        pht(
          'Object ("%s") does not implement interface "%s", so this type '.
          'of object can not be unlocked.',
          phutil_describe_type($object),
          'PhabricatorApplicationTransactionInterface'));
    }

    if ($object instanceof PhabricatorUnlockableInterface) {
      $engine = $object->newUnlockEngine();
    } else {
      $engine = new PhabricatorDefaultUnlockEngine();
    }

    return $engine;
  }

  public function newUnlockViewTransactions($object, $user) {
    $type_view = PhabricatorTransactions::TYPE_VIEW_POLICY;

    if (!$this->canApplyTransactionType($object, $type_view)) {
      throw new Exception(
        pht(
          'Object view policy can not be unlocked because this object '.
          'does not have a mutable view policy.'));
    }

    return array(
      $this->newTransaction($object)
        ->setTransactionType($type_view)
        ->setNewValue($user->getPHID()),
    );
  }

  public function newUnlockEditTransactions($object, $user) {
    $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;

    if (!$this->canApplyTransactionType($object, $type_edit)) {
      throw new Exception(
        pht(
          'Object edit policy can not be unlocked because this object '.
          'does not have a mutable edit policy.'));
    }

    return array(
      $this->newTransaction($object)
        ->setTransactionType($type_edit)
        ->setNewValue($user->getPHID()),
    );
  }

  public function newUnlockOwnerTransactions($object, $user) {
    throw new Exception(
      pht(
        'Object owner can not be unlocked: the unlocking engine ("%s") for '.
        'this object does not implement an owner unlocking mechanism.',
        get_class($this)));
  }

  final protected function canApplyTransactionType($object, $type) {
    $xaction_types = $object->getApplicationTransactionEditor()
      ->getTransactionTypesForObject($object);

    $xaction_types = array_fuse($xaction_types);

    return isset($xaction_types[$type]);
  }

  final protected function newTransaction($object) {
    return $object->getApplicationTransactionTemplate();
  }


}
