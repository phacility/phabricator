<?php

final class PhabricatorAuthMFAEditEngineExtension
  extends PhabricatorEditEngineExtension {

  const EXTENSIONKEY = 'auth.mfa';
  const FIELDKEY = 'mfa';

  public function getExtensionPriority() {
    return 12000;
  }

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('MFA');
  }

  public function supportsObject(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {
    return true;
  }

  public function buildCustomEditFields(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {

    $mfa_type = PhabricatorTransactions::TYPE_MFA;

    $viewer = $engine->getViewer();

    $mfa_field = id(new PhabricatorApplyEditField())
      ->setViewer($viewer)
      ->setKey(self::FIELDKEY)
      ->setLabel(pht('MFA'))
      ->setIsFormField(false)
      ->setCommentActionLabel(pht('Sign With MFA'))
      ->setCanApplyWithoutEditCapability(true)
      ->setCommentActionOrder(12000)
      ->setActionDescription(
        pht('You will be prompted to provide MFA when you submit.'))
      ->setDescription(pht('Sign this transaction group with MFA.'))
      ->setTransactionType($mfa_type);

    return array(
      $mfa_field,
    );
  }

}
