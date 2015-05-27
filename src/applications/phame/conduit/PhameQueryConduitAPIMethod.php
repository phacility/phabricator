<?php

final class PhameQueryConduitAPIMethod extends PhameConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phame.query';
  }

  public function getMethodDescription() {
    return pht('Query phame blogs.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  protected function defineParamTypes() {
    return array(
      'ids'           => 'optional list<int>',
      'phids'         => 'optional list<phid>',
      'after'         => 'optional int',
      'before'        => 'optional int',
      'limit'         => 'optional int',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = new PhameBlogQuery();

    $query->setViewer($request->getUser());

    $ids = $request->getValue('ids', array());
    if ($ids) {
      $query->withIDs($ids);
    }

    $phids = $request->getValue('phids', array());
    if ($phids) {
      $query->withPHIDs($phids);
    }

    $after = $request->getValue('after', null);
    if ($after !== null) {
      $query->setAfterID($after);
    }

    $before = $request->getValue('before', null);
    if ($before !== null) {
      $query->setBeforeID($before);
    }

    $limit = $request->getValue('limit', null);
    if ($limit !== null) {
      $query->setLimit($limit);
    }

    $blogs = $query->execute();

    $results = array();
    foreach ($blogs as $blog) {
      $results[] = array(
        'id'              => $blog->getID(),
        'phid'            => $blog->getPHID(),
        'name'            => $blog->getName(),
        'description'     => $blog->getDescription(),
        'domain'          => $blog->getDomain(),
        'creatorPHID'     => $blog->getCreatorPHID(),
      );
    }

    return $results;
  }

}
