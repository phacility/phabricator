<?php

final class PhabricatorProjectTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_EDGE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_EDGE:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_EDGE:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
    }

    return $errors;
  }

  protected function requireCapabilities(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_EDGE:
        switch ($xaction->getMetadataValue('edge:type')) {
          case PhabricatorEdgeConfig::TYPE_PROJ_MEMBER:
            $old = $xaction->getOldValue();
            $new = $xaction->getNewValue();

            $add = array_keys(array_diff_key($new, $old));
            $rem = array_keys(array_diff_key($old, $new));

            $actor_phid = $this->requireActor()->getPHID();

            $is_join = (($add === array($actor_phid)) && !$rem);
            $is_leave = (($rem === array($actor_phid)) && !$add);

            if ($is_join) {
              // You need CAN_JOIN to join a project.
              PhabricatorPolicyFilter::requireCapability(
                $this->requireActor(),
                $object,
                PhabricatorPolicyCapability::CAN_JOIN);
            } else if ($is_leave) {
              // You don't need any capabilities to leave a project.
            } else {
              // You need CAN_EDIT to change members other than yourself.
              PhabricatorPolicyFilter::requireCapability(
                $this->requireActor(),
                $object,
                PhabricatorPolicyCapability::CAN_EDIT);
            }
            return;
        }
        break;
    }

    return parent::requireCapabilities();
  }

}
