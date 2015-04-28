<?php

final class TokenGivenConduitAPIMethod extends TokenConduitAPIMethod {

  public function getAPIMethodName() {
    return 'token.given';
  }

  public function getMethodDescription() {
    return pht('Query tokens given to objects.');
  }

  protected function defineParamTypes() {
    return array(
      'authorPHIDs' => 'list<phid>',
      'objectPHIDs' => 'list<phid>',
      'tokenPHIDs'  => 'list<phid>',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = id(new PhabricatorTokenGivenQuery())
      ->setViewer($request->getUser());

    $author_phids = $request->getValue('authorPHIDs');
    if ($author_phids) {
      $query->withAuthorPHIDs($author_phids);
    }

    $object_phids = $request->getValue('objectPHIDs');
    if ($object_phids) {
      $query->withObjectPHIDs($object_phids);
    }

    $token_phids = $request->getValue('tokenPHIDs');
    if ($token_phids) {
      $query->withTokenPHIDs($token_phids);
    }

    $given = $query->execute();

    return $this->buildTokenGivenDicts($given);
  }

}
