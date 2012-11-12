<?php

/**
 * @group conduit
 */
final class ConduitAPI_paste_query_Method extends ConduitAPI_paste_Method {

  public function getMethodDescription() {
    return "Query Pastes.";
  }

  public function defineParamTypes() {
    return array(
      'ids'           => 'optional list<int>',
      'phids'         => 'optional list<phid>',
      'authorPHIDs'   => 'optional list<phid>',
      'after'         => 'optional int',
      'limit'         => 'optional int, default = 100',
    );
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = id(new PhabricatorPasteQuery())
      ->setViewer($request->getUser())
      ->needContent(true);

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
