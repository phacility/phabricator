<?php

final class HarbormasterBuildkiteHookController
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

    $event = idx($body, 'event');
    if ($event != 'build.finished') {
      return $this->newHookResponse(pht('OK: Ignored event.'));
    }

    $build = idx($body, 'build');
    if (!is_array($build)) {
      throw new Exception(
        pht(
          'Expected "%s" property to contain a dictionary.',
          'build'));
    }

    $meta_data = idx($build, 'meta_data');
    if (!is_array($meta_data)) {
      throw new Exception(
        pht(
          'Expected "%s" property to contain a dictionary.',
          'build.meta_data'));
    }

    $target_phid = idx($meta_data, 'buildTargetPHID');
    if (!$target_phid) {
      return $this->newHookResponse(pht('OK: No Harbormaster target PHID.'));
    }

    $viewer = PhabricatorUser::getOmnipotentUser();
    $target = id(new HarbormasterBuildTargetQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($target_phid))
      ->needBuildSteps(true)
      ->executeOne();
    if (!$target) {
      throw new Exception(
        pht(
          'Harbormaster build target "%s" does not exist.',
          $target_phid));
    }

    $step = $target->getBuildStep();
    $impl = $step->getStepImplementation();
    if (!($impl instanceof HarbormasterBuildkiteBuildStepImplementation)) {
      throw new Exception(
        pht(
          'Harbormaster build target "%s" is not a Buildkite build step. '.
          'Only Buildkite steps may be updated via the Buildkite hook.',
          $target_phid));
    }

    $webhook_token = $impl->getSetting('webhook.token');
    $request_token = $request->getHTTPHeader('X-Buildkite-Token');

    if (!phutil_hashes_are_identical($webhook_token, $request_token)) {
      throw new Exception(
        pht(
          'Buildkite request to target "%s" had the wrong authentication '.
          'token. The Buildkite pipeline and Harbormaster build step must '.
          'be configured with the same token.',
          $target_phid));
    }

    $state = idx($build, 'state');
    switch ($state) {
      case 'passed':
        $message_type = HarbormasterMessageType::MESSAGE_PASS;
        break;
      default:
        $message_type = HarbormasterMessageType::MESSAGE_FAIL;
        break;
    }

    $api_method = 'harbormaster.sendmessage';
    $api_params = array(
      'buildTargetPHID' => $target_phid,
      'type' => $message_type,
    );

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

      id(new ConduitCall($api_method, $api_params))
        ->setUser($viewer)
        ->execute();

    unset($unguarded);

    return $this->newHookResponse(pht('OK: Processed event.'));
  }

  private function newHookResponse($message) {
    $response = new AphrontWebpageResponse();
    $response->setContent($message);
    return $response;
  }

}
