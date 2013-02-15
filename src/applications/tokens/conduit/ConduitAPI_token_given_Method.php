<?php

final class ConduitAPI_token_given_Method extends ConduitAPI_token_Method {

  public function getMethodDescription() {
    return pht('Query tokens given to objects.');
  }

  public function defineParamTypes() {
    return array(
      'authorPHIDs' => 'list<phid>',
      'objectPHIDs' => 'list<phid>',
      'tokenPHIDs'  => 'list<phid>',
    );
  }

  public function defineErrorTypes() {
    return array();
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function execute(ConduitAPIRequest $request) {
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
