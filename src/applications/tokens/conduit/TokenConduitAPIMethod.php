<?php

abstract class TokenConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorTokensApplication');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function buildTokenDicts(array $tokens) {
    assert_instances_of($tokens, 'PhabricatorToken');

    $list = array();
    foreach ($tokens as $token) {
      $list[] = array(
        'id' => $token->getID(),
        'name' => $token->getName(),
        'phid' => $token->getPHID(),
      );
    }

    return $list;
  }

  public function buildTokenGivenDicts(array $tokens_given) {
    assert_instances_of($tokens_given, 'PhabricatorTokenGiven');

    $list = array();
    foreach ($tokens_given as $given) {
      $list[] = array(
        'authorPHID'  => $given->getAuthorPHID(),
        'objectPHID'  => $given->getObjectPHID(),
        'tokenPHID'   => $given->getTokenPHID(),
        'dateCreated' => $given->getDateCreated(),
      );
    }

    return $list;
  }

}
