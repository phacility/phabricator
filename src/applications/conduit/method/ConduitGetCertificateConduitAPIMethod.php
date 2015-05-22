<?php

final class ConduitGetCertificateConduitAPIMethod extends ConduitAPIMethod {

  public function getAPIMethodName() {
    return 'conduit.getcertificate';
  }

  public function shouldRequireAuthentication() {
    return false;
  }

  public function shouldAllowUnguardedWrites() {
    // This method performs logging and is on the authentication pathway.
    return true;
  }

  public function getMethodDescription() {
    return pht('Retrieve certificate information for a user.');
  }

  protected function defineParamTypes() {
    return array(
      'token' => 'required string',
      'host'  => 'required string',
    );
  }

  protected function defineReturnType() {
    return 'dict<string, any>';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-BAD-TOKEN' => pht('Token does not exist or has expired.'),
      'ERR-RATE-LIMIT' => pht(
        'You have made too many invalid token requests recently. Wait before '.
        'making more.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $failed_attempts = PhabricatorUserLog::loadRecentEventsFromThisIP(
      PhabricatorUserLog::ACTION_CONDUIT_CERTIFICATE_FAILURE,
      60 * 5);

    if (count($failed_attempts) > 5) {
      $this->logFailure($request);
      throw new ConduitException('ERR-RATE-LIMIT');
    }

    $token = $request->getValue('token');
    $info = id(new PhabricatorConduitCertificateToken())->loadOneWhere(
      'token = %s',
      trim($token));

    if (!$info || $info->getDateCreated() < time() - (60 * 15)) {
      $this->logFailure($request, $info);
      throw new ConduitException('ERR-BAD-TOKEN');
    } else {
      $log = PhabricatorUserLog::initializeNewLog(
          $request->getUser(),
          $info->getUserPHID(),
          PhabricatorUserLog::ACTION_CONDUIT_CERTIFICATE)
        ->save();
    }

    $user = id(new PhabricatorUser())->loadOneWhere(
      'phid = %s',
      $info->getUserPHID());
    if (!$user) {
      throw new Exception(pht('Certificate token points to an invalid user!'));
    }

    return array(
      'username'    => $user->getUserName(),
      'certificate' => $user->getConduitCertificate(),
    );
  }

  private function logFailure(
    ConduitAPIRequest $request,
    PhabricatorConduitCertificateToken $info = null) {

    $log = PhabricatorUserLog::initializeNewLog(
        $request->getUser(),
        $info ? $info->getUserPHID() : '-',
        PhabricatorUserLog::ACTION_CONDUIT_CERTIFICATE_FAILURE)
      ->save();
  }

}
