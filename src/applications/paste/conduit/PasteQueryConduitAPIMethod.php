<?php

final class PasteQueryConduitAPIMethod extends PasteConduitAPIMethod {

  public function getAPIMethodName() {
    return 'paste.query';
  }

  public function getMethodDescription() {
    return pht('Query Pastes.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "paste.search" instead.');
  }

  protected function defineParamTypes() {
    return array(
      'ids'           => 'optional list<int>',
      'phids'         => 'optional list<phid>',
      'authorPHIDs'   => 'optional list<phid>',
      'after'         => 'optional int',
      'limit'         => 'optional int, default = 100',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = id(new PhabricatorPasteQuery())
      ->setViewer($request->getUser())
      ->needRawContent(true);

    if ($request->getValue('ids')) {
      $query->withIDs($request->getValue('ids'));
    }

    if ($request->getValue('phids')) {
      $query->withPHIDs($request->getValue('phids'));
    }

    if ($request->getValue('authorPHIDs')) {
      $query->withAuthorPHIDs($request->getValue('authorPHIDs'));
    }

    if ($request->getValue('after')) {
      $query->setAfterID($request->getValue('after'));
    }

    $limit = $request->getValue('limit', 100);
    if ($limit) {
      $query->setLimit($limit);
    }

    $pastes = $query->execute();

    $results = array();
    foreach ($pastes as $paste) {
      $results[$paste->getPHID()] = $this->buildPasteInfoDictionary($paste);
    }

    return $results;
  }

}
