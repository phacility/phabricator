<?php

final class HarbormasterCircleCIHookController
  extends HarbormasterController {

  public function shouldRequireLogin() {
    return false;
  }

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  public function handleRequest(AphrontRequest $request) {
    $raw_body = PhabricatorStartup::getRawInput();
    $body = phutil_json_decode($raw_body);

    $payload = $body['payload'];

    $parameters = idx($payload, 'build_parameters');
    if (!$parameters) {
      $parameters = array();
    }

    $target_phid = idx($parameters, 'HARBORMASTER_BUILD_TARGET_PHID');

    // NOTE: We'll get callbacks here for builds we triggered, but also for
    // arbitrary builds the system executes for other reasons. So it's normal
    // to get some notifications with no Build Target PHID. We just ignore
    // these under the assumption that they're routine builds caused by events
    // like branch updates.

    if ($target_phid) {
      $viewer = PhabricatorUser::getOmnipotentUser();
      $target = id(new HarbormasterBuildTargetQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($target_phid))
        ->needBuildSteps(true)
        ->executeOne();
      if ($target) {
        $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $this->updateTarget($target, $payload);
      }
    }

    $response = new AphrontWebpageResponse();
    $response->setContent(pht("Request OK\n"));
    return $response;
  }

  private function updateTarget(
    HarbormasterBuildTarget $target,
    array $payload) {

    $step = $target->getBuildStep();
    $impl = $step->getStepImplementation();
    if (!($impl instanceof HarbormasterCircleCIBuildStepImplementation)) {
      throw new Exception(
        pht(
          'Build target ("%s") has the wrong type of build step. Only '.
          'CircleCI build steps may be updated via the CircleCI webhook.',
          $target->getPHID()));
    }

    switch (idx($payload, 'status')) {
      case 'success':
      case 'fixed':
        $message_type = HarbormasterMessageType::MESSAGE_PASS;
        break;
      default:
        $message_type = HarbormasterMessageType::MESSAGE_FAIL;
        break;
    }

    $viewer = PhabricatorUser::getOmnipotentUser();

    $api_method = 'harbormaster.sendmessage';
    $api_params = array(
      'buildTargetPHID' => $target->getPHID(),
      'type' => $message_type,
    );

    id(new ConduitCall($api_method, $api_params))
      ->setUser($viewer)
      ->execute();
  }

}
