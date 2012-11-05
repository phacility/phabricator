<?php

/**
 * @group conduit
 */
final class ConduitAPI_project_query_Method extends ConduitAPI_project_Method {

  public function getMethodDescription() {
    return "Execute searches for Projects.";
  }

  public function defineParamTypes() {

    $statuses = array(
      PhabricatorProjectQuery::STATUS_ANY,
      PhabricatorProjectQuery::STATUS_OPEN,
      PhabricatorProjectQuery::STATUS_CLOSED,
      PhabricatorProjectQuery::STATUS_ACTIVE,
      PhabricatorProjectQuery::STATUS_ARCHIVED,
    );

    return array(
      'ids'               => 'optional list<int>',
      'phids'             => 'optional list<phid>',
      'status'            => 'optional enum<'.implode(', ', $statuses).'>',

      'members'           => 'optional list<phid>',

      'limit'             => 'optional int',
      'offset'            => 'optional int',
    );
  }

  public function defineReturnType() {
    return 'list';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $query = new PhabricatorProjectQuery();
    $query->setViewer($request->getUser());
    $query->needMembers(true);

    $ids = $request->getValue('ids');
    if ($ids) {
      $query->withIDs($ids);
    }

    $status = $request->getValue('status');
    if ($status) {
      $query->withStatus($status);
    }

    $phids = $request->getValue('phids');
    if ($phids) {
      $query->withPHIDs($phids);
    }

    $members = $request->getValue('members');
    if ($members) {
      $query->withMemberPHIDs($members);
    }

    $limit = $request->getValue('limit');
    if ($limit) {
      $query->setLimit($limit);
    }

    $offset = $request->getValue('offset');
    if ($offset) {
      $query->setOffset($offset);
    }

    $results = $query->execute();
    return $this->buildProjectInfoDictionaries($results);
  }

}
