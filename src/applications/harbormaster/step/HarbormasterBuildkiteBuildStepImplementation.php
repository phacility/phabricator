<?php

final class HarbormasterBuildkiteBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Build with Buildkite');
  }

  public function getGenericDescription() {
    return pht('Trigger a build in Buildkite.');
  }

  public function getBuildStepGroupKey() {
    return HarbormasterExternalBuildStepGroup::GROUPKEY;
  }

  public function getDescription() {
    return pht('Run a build in Buildkite.');
  }

  public function getEditInstructions() {
    $hook_uri = '/harbormaster/hook/buildkite/';
    $hook_uri = PhabricatorEnv::getProductionURI($hook_uri);

    return pht(<<<EOTEXT
WARNING: This build step is new and experimental!

To build **revisions** with Buildkite, they must:

  - belong to a tracked repository;
  - the repository must have a Staging Area configured;
  - you must configure a Buildkite pipeline for that Staging Area; and
  - you must configure the webhook described below.

To build **commits** with Buildkite, they must:

  - belong to a tracked repository;
  - you must configure a Buildkite pipeline for that repository; and
  - you must configure the webhook described below.

Webhook Configuration
=====================

In {nav Settings} for your Organization in Buildkite, under
{nav Notification Services}, add a new **Webook Notification**.

Use these settings:

  - **Webhook URL**: %s
  - **Token**: The "Webhook Token" field below and the "Token" field in
    Buildkite should both be set to the same nonempty value (any random
    secret). You can use copy/paste the value Buildkite generates into
    this form.
  - **Events**: Only **build.finish** needs to be active.

Environment
===========

These variables will be available in the build environment:

| Variable | Description |
|----------|-------------|
| `HARBORMASTER_BUILD_TARGET_PHID` | PHID of the Build Target.
EOTEXT
    ,
    $hook_uri);
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $buildable = $build->getBuildable();

    $object = $buildable->getBuildableObject();
    if (!($object instanceof HarbormasterBuildkiteBuildableInterface)) {
      throw new Exception(
        pht('This object does not support builds with Buildkite.'));
    }

    $organization = $this->getSetting('organization');
    $pipeline = $this->getSetting('pipeline');

    $uri = urisprintf(
      'https://api.buildkite.com/v2/organizations/%s/pipelines/%s/builds',
      $organization,
      $pipeline);

    $data_structure = array(
      'commit' => $object->getBuildkiteCommit(),
      'branch' => $object->getBuildkiteBranch(),
      'message' => pht(
        'Harbormaster Build %s ("%s") for %s',
        $build->getID(),
        $build->getName(),
        $buildable->getMonogram()),
      'env' => array(
        'HARBORMASTER_BUILD_TARGET_PHID' => $build_target->getPHID(),
      ),
      'meta_data' => array(
        'buildTargetPHID' => $build_target->getPHID(),
      ),
    );

    $json_data = phutil_json_encode($data_structure);

    $credential_phid = $this->getSetting('token');
    $api_token = id(new PassphraseCredentialQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($credential_phid))
      ->needSecrets(true)
      ->executeOne();
    if (!$api_token) {
      throw new Exception(
        pht(
          'Unable to load API token ("%s")!',
          $credential_phid));
    }

    $token = $api_token->getSecret()->openEnvelope();

    $future = id(new HTTPSFuture($uri, $json_data))
      ->setMethod('POST')
      ->addHeader('Content-Type', 'application/json')
      ->addHeader('Accept', 'application/json')
      ->addHeader('Authorization', "Bearer {$token}")
      ->setTimeout(60);

    $this->resolveFutures(
      $build,
      $build_target,
      array($future));

    $this->logHTTPResponse($build, $build_target, $future, pht('Buildkite'));

    list($status, $body) = $future->resolve();
    if ($status->isError()) {
      throw new HarbormasterBuildFailureException();
    }

    $response = phutil_json_decode($body);

    $uri_key = 'web_url';
    $build_uri = idx($response, $uri_key);
    if (!$build_uri) {
      throw new Exception(
        pht(
          'Buildkite did not return a "%s"!',
          $uri_key));
    }

    $target_phid = $build_target->getPHID();

    $api_method = 'harbormaster.createartifact';
    $api_params = array(
      'buildTargetPHID' => $target_phid,
      'artifactType' => HarbormasterURIArtifact::ARTIFACTCONST,
      'artifactKey' => 'buildkite.uri',
      'artifactData' => array(
        'uri' => $build_uri,
        'name' => pht('View in Buildkite'),
        'ui.external' => true,
      ),
    );

    id(new ConduitCall($api_method, $api_params))
      ->setUser($viewer)
      ->execute();
  }

  public function getFieldSpecifications() {
    return array(
      'token' => array(
        'name' => pht('API Token'),
        'type' => 'credential',
        'credential.type'
          => PassphraseTokenCredentialType::CREDENTIAL_TYPE,
        'credential.provides'
          => PassphraseTokenCredentialType::PROVIDES_TYPE,
        'required' => true,
      ),
      'organization' => array(
        'name' => pht('Organization Name'),
        'type' => 'text',
        'required' => true,
      ),
      'pipeline' => array(
        'name' => pht('Pipeline Name'),
        'type' => 'text',
        'required' => true,
      ),
      'webhook.token' => array(
        'name' => pht('Webhook Token'),
        'type' => 'text',
        'required' => true,
      ),
    );
  }

  public function supportsWaitForMessage() {
    return false;
  }

  public function shouldWaitForMessage(HarbormasterBuildTarget $target) {
    return true;
  }

}
