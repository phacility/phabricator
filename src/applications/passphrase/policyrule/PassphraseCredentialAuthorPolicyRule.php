<?php

final class PassphraseCredentialAuthorPolicyRule
  extends PhabricatorPolicyRule {

  public function getObjectPolicyKey() {
    return 'passphrase.author';
  }

  public function getObjectPolicyName() {
    return pht('Credential Author');
  }

  public function getPolicyExplanation() {
    return pht('The author of this credential can take this action.');
  }

  public function getRuleDescription() {
    return pht('credential author');
  }

  public function canApplyToObject(PhabricatorPolicyInterface $object) {
    return ($object instanceof PassphraseCredential);
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {

    $author_phid = $object->getAuthorPHID();
    if (!$author_phid) {
      return false;
    }

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return false;
    }

    return ($viewer_phid == $author_phid);
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_NONE;
  }

}
