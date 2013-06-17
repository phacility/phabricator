<?php

final class PhabricatorAuthProviderConfigEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorAuthProviderConfigTransaction::TYPE_ENABLE;
    $types[] = PhabricatorAuthProviderConfigTransaction::TYPE_REGISTRATION;
    $types[] = PhabricatorAuthProviderConfigTransaction::TYPE_LINK;
    $types[] = PhabricatorAuthProviderConfigTransaction::TYPE_UNLINK;
    $types[] = PhabricatorAuthProviderConfigTransaction::TYPE_PROPERTY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuthProviderConfigTransaction::TYPE_ENABLE:
        return $object->getIsEnabled();
      case PhabricatorAuthProviderConfigTransaction::TYPE_REGISTRATION:
        return $object->getShouldAllowRegistration();
      case PhabricatorAuthProviderConfigTransaction::TYPE_LINK:
        return $object->getShouldAllowLink();
      case PhabricatorAuthProviderConfigTransaction::TYPE_UNLINK:
        return $object->getShouldAllowUnlink();
      case PhabricatorAuthProviderConfigTransaction::TYPE_PROPERTY:
        // TODO
        throw new Exception("TODO");
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuthProviderConfigTransaction::TYPE_ENABLE:
      case PhabricatorAuthProviderConfigTransaction::TYPE_REGISTRATION:
      case PhabricatorAuthProviderConfigTransaction::TYPE_LINK:
      case PhabricatorAuthProviderConfigTransaction::TYPE_UNLINK:
      case PhabricatorAuthProviderConfigTransaction::TYPE_PROPERTY:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $v = $xaction->getNewValue();
    switch ($xaction->getTransactionType()) {
      case PhabricatorAuthProviderConfigTransaction::TYPE_ENABLE:
        return $object->setIsEnabled($v);
      case PhabricatorAuthProviderConfigTransaction::TYPE_REGISTRATION:
        return $object->setShouldAllowRegistration($v);
      case PhabricatorAuthProviderConfigTransaction::TYPE_LINK:
        return $object->setShouldAllowLink($v);
      case PhabricatorAuthProviderConfigTransaction::TYPE_UNLINK:
        return $object->setShouldAllowUnlink($v);
      case PhabricatorAuthProviderConfigTransaction::TYPE_PROPERTY:
        // TODO
        throw new Exception("TODO");
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();
    switch ($type) {
      case PhabricatorAuthProviderConfigTransaction::TYPE_ENABLE:
      case PhabricatorAuthProviderConfigTransaction::TYPE_REGISTRATION:
      case PhabricatorAuthProviderConfigTransaction::TYPE_LINK:
      case PhabricatorAuthProviderConfigTransaction::TYPE_UNLINK:
        // For these types, last transaction wins.
        return $v;
    }

    return parent::mergeTransactions($u, $v);
  }

}
