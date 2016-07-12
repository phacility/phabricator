<?php

final class UserQueryConduitAPIMethod extends UserConduitAPIMethod {

  public function getAPIMethodName() {
    return 'user.query';
  }

  public function getMethodDescription() {
    return pht('Query users.');
  }

  protected function defineParamTypes() {
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

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-INVALID-PARAMETER' => pht('Missing or malformed parameter.'),
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

    $query = id(new PhabricatorPeopleQuery())
      ->setViewer($request->getUser())
      ->needProfileImage(true)
      ->needAvailability(true);

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

    $results = array();
    foreach ($users as $user) {
      $results[] = $this->buildUserInformationDictionary(
        $user,
        $with_email = false,
        $with_availability = true);
    }
    return $results;
  }

}
