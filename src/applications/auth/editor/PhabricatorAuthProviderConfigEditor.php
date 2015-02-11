<?php

final class PhabricatorAuthProviderConfigEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Auth Providers');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorAuthProviderConfigTransaction::TYPE_ENABLE;
    $types[] = PhabricatorAuthProviderConfigTransaction::TYPE_REGISTRATION;
    $types[] = PhabricatorAuthProviderConfigTransaction::TYPE_LINK;
    $types[] = PhabricatorAuthProviderConfigTransaction::TYPE_UNLINK;
    $types[] = PhabricatorAuthProviderConfigTransaction::TYPE_TRUST_EMAILS;
    $types[] = PhabricatorAuthProviderConfigTransaction::TYPE_AUTO_LOGIN;
    $types[] = PhabricatorAuthProviderConfigTransaction::TYPE_PROPERTY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuthProviderConfigTransaction::TYPE_ENABLE:
        if ($object->getIsEnabled() === null) {
          return null;
        } else {
          return (int)$object->getIsEnabled();
        }
      case PhabricatorAuthProviderConfigTransaction::TYPE_REGISTRATION:
        return (int)$object->getShouldAllowRegistration();
      case PhabricatorAuthProviderConfigTransaction::TYPE_LINK:
        return (int)$object->getShouldAllowLink();
      case PhabricatorAuthProviderConfigTransaction::TYPE_UNLINK:
        return (int)$object->getShouldAllowUnlink();
      case PhabricatorAuthProviderConfigTransaction::TYPE_TRUST_EMAILS:
        return (int)$object->getShouldTrustEmails();
      case PhabricatorAuthProviderConfigTransaction::TYPE_AUTO_LOGIN:
        return (int)$object->getShouldAutoLogin();
      case PhabricatorAuthProviderConfigTransaction::TYPE_PROPERTY:
        $key = $xaction->getMetadataValue(
          PhabricatorAuthProviderConfigTransaction::PROPERTY_KEY);
        return $object->getProperty($key);
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
      case PhabricatorAuthProviderConfigTransaction::TYPE_TRUST_EMAILS:
      case PhabricatorAuthProviderConfigTransaction::TYPE_AUTO_LOGIN:
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
      case PhabricatorAuthProviderConfigTransaction::TYPE_TRUST_EMAILS:
        return $object->setShouldTrustEmails($v);
      case PhabricatorAuthProviderConfigTransaction::TYPE_AUTO_LOGIN:
        return $object->setShouldAutoLogin($v);
      case PhabricatorAuthProviderConfigTransaction::TYPE_PROPERTY:
        $key = $xaction->getMetadataValue(
          PhabricatorAuthProviderConfigTransaction::PROPERTY_KEY);
        return $object->setProperty($key, $v);
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
      case PhabricatorAuthProviderConfigTransaction::TYPE_TRUST_EMAILS:
      case PhabricatorAuthProviderConfigTransaction::TYPE_AUTO_LOGIN:
        // For these types, last transaction wins.
        return $v;
    }

    return parent::mergeTransactions($u, $v);
  }

}
