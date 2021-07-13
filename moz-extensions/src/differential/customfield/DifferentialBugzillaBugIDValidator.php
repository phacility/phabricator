<?php

class DifferentialBugzillaBugIDValidator extends Phobject {

  // Logging error key type
  const LOGGING_TYPE = 'mozphab.bugfield.validator';

  public static function formatBugID($id) {
    return trim(str_replace('#', '', $id));
  }

  public static function validate($bug_id, $account_phid) {
    // This function returns an array of strings representing errors
    // If this function returns an empty array, all data is valid
    $errors = array();

    $bug_id = self::formatBugID($bug_id);

    // Check for bug ID which may or may not be required at a given time
    if(!strlen($bug_id)) {
      if(PhabricatorEnv::getEnvConfig('bugzilla.require_bugs') === true) {
        $errors[] = pht('Bugzilla Bug ID is required');
      }
      return $errors;
    }

    // Isn't a number we can work with
    if(!ctype_digit($bug_id) || $bug_id === '0') {
      $errors[] = pht('Bugzilla Bug ID must be a valid bug number');
      return $errors;
    }

    // Make a request to BMO to ensure the bug exists and user can see it

    // Check to see if the user is an admin; if so, don't validate bug existence
    // because the admin account may not have a BMO account ID associated with it
    // and we don't want to block admins from making revisions private
    // if BMO is down
    $users = id(new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($account_phid))
      ->withIsAdmin(true)
      ->execute();
    if(count($users)) {
      return $errors;
    }

    // Get the transactor's ExternalAccount ID using the author's phid
    $config = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withProviderClasses(array('PhabricatorBMOAuthProvider'))
      ->executeOne();

    $users = id(new PhabricatorExternalAccountQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withProviderConfigPHIDs(array($config->getPHID()))
      ->withUserPHIDs(array($account_phid))
      ->needAccountIdentifiers(true)
      ->execute();

    // The only way this should happen is if the user creating/editing the
    // revision isn't tied to a BMO account id (i.e. traditional Phab registration)
    if(!count($users)) {
      $errors[] = pht('This transaction\'s user\'s account ID could not be found.');
      return $errors;
    }
    $user_detail = reset($users);
    $identifiers = $user_detail->getAccountIdentifiers();
    $user_bmo_id = head($identifiers)->getIdentifierRaw();

    if (!$user_bmo_id) {
      $errors[] = pht('No Bugzilla account identifier found for current Phabricator user.');
      return $errors;
    }

    $future_uri = id(new PhutilURI(PhabricatorEnv::getEnvConfig('bugzilla.url')))
      ->setPath('/rest/phabbugz/check_bug/'.$bug_id.'/'.$user_bmo_id);

    $future = id(new HTTPSFuture((string) $future_uri))
      ->setMethod('GET')
      ->addHeader('X-Bugzilla-API-Key', PhabricatorEnv::getEnvConfig('bugzilla.automation_api_key'))
      ->addHeader('Accept', 'application/json')
      ->addHeader('User-Agent', 'Phabricator')
      ->setTimeout(PhabricatorEnv::getEnvConfig('bugzilla.timeout'));

    // Resolve the async HTTPSFuture request and extract JSON body
    try {
      list($status, $body) = $future->resolve();
      $status_code = (int) $status->getStatusCode();

      // (Successful Request)
      if($status_code === 200) {
        $json = phutil_json_decode($body);
        // A "result" of "1" means the bug exists and the user can see it
        // Any other result is invalid and should raise an error
        if($json['result'] != '1') {
          $errors[] = pht('Bugzilla Bug ID:  You do not have permission to view this bug or the bug does not exist.');
        }

        // Everything is good!  Return empty error array
        return $errors;
      }
      // 100 (Invalid Bug Alias) If you specified an alias and there is no bug with that alias.
      // 101 (Invalid Bug ID) The bug_id you specified doesn't exist in the database.
      // 404 (URL Not Found)
      else if(in_array($status_code, array(100, 101, 404))) {
        $errors[] = pht('Bugzilla Bug ID: %s does not exist (%s).', $bug_id, $status_code);
      }
      // (Access Denied) You do not have access to the bug_id you specified.
      else if($status_code === 102) {
        $errors[] = pht('Bugzilla Bug ID: You do not have permission to view this bug.');
      }
      // (BMO is down or API is having a problem)
      else if($status_code === 500) {
        $errors[] = pht('Bugzilla Bug ID: Bugzilla responded with a 500 error.');
      }
      // Anything other than standard response codes
      else {
        $unkown_bmo_status_error = pht('Bugzilla Bug ID: Bugzilla did not provide an expected response (%s).', $status_code);

        $errors[] = $unkown_bmo_status_error;
        MozLogger::log(
          $unkown_bmo_status_error,
          self::LOGGING_TYPE,
          array('Fields' => array(
            'status_code' => $status_code,
            'bug_id' => $bug_id,
            'body' => $body,
            'account_phid' => $account_phid
          ))
        );
      }
    } catch (HTTPFutureResponseStatus $ex) {
      // This case could be interruption in connection to BMO
      $future_error = pht('Bugzilla Bug ID: Bugzilla did not provide an expected response (%s).', $ex->getStatusCode());

      $errors[] = $future_error;
      MozLogger::log(
        $future_error,
        self::LOGGING_TYPE,
        array('Fields' => array(
          'status_code' => $ex->getStatusCode(),
          'bug_id' => $bug_id,
          'account_phid' => $account_phid
        ))
      );
    }

    return $errors;
  }
}
