<?php

/**
 * @group conduit
 */
final class ConduitAPI_user_query_Method
  extends ConduitAPI_user_Method {

  public function getMethodDescription() {
    return "Query users.";
  }

  public function defineParamTypes() {

    return array(
      'usernames'    => 'optional list<string>',
      'emails'       => 'optional list<string>',
      'realnames'    => 'optional list<string>',
      'phids'        => 'optional list<phid>',
      'ids'          => 'optional list<uint>',
      'offset'       => 'optional int',
      'limit'        => 'optional int (default = 100)',
    );

  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' => 'Missing or malformed parameter.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $usernames   = $request->getValue('usernames', array());
    $emails      = $request->getValue('emails',    array());
    $realnames   = $request->getValue('realnames', array());
    $phids       = $request->getValue('phids',     array());
    $ids         = $request->getValue('ids',       array());
    $offset      = $request->getValue('offset',    0);
    $limit       = $request->getValue('limit',     100);

    $query = new PhabricatorPeopleQuery();
    if ($usernames) {
      $query->withUsernames($usernames);
    }
    if ($emails) {
      $query->withEmails($emails);
    }
    if ($realnames) {
      $query->withRealnames($realnames);
    }
    if ($phids) {
      $query->withPHIDs($phids);
    }
    if ($ids) {
      $query->withIDs($ids);
    }
    if ($limit) {
      $query->setLimit($limit);
    }
    if ($offset) {
      $query->setOffset($offset);
    }
    $users = $query->execute();

    $statuses = id(new PhabricatorUserStatus())->loadCurrentStatuses(
      mpull($users, 'getPHID'));

    $results = array();
    foreach ($users as $user) {
      $results[] = $this->buildUserInformationDictionary(
        $user,
        idx($statuses, $user->getPHID()));
    }
    return $results;
  }
}
