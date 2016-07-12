<?php

final class HarbormasterCircleCIBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Build with CircleCI');
  }

  public function getGenericDescription() {
    return pht('Trigger a build in CircleCI.');
  }

  public function getBuildStepGroupKey() {
    return HarbormasterExternalBuildStepGroup::GROUPKEY;
  }

  public function getDescription() {
    return pht('Run a build in CircleCI.');
  }

  public function getEditInstructions() {
    $hook_uri = '/harbormaster/hook/circleci/';
    $hook_uri = PhabricatorEnv::getProductionURI($hook_uri);

    return pht(<<<EOTEXT
WARNING: This build step is new and experimental!

To build **revisions** with CircleCI, they must:

  - belong to a tracked repository;
  - the repository must have a Staging Area configured;
  - the Staging Area must be hosted on GitHub; and
  - you must configure the webhook described below.

To build **commits** with CircleCI, they must:

  - belong to a repository that is being imported from GitHub; and
  - you must configure the webhook described below.

Webhook Configuration
=====================

Add this webhook to your `circle.yml` file to make CircleCI report results
to Harbormaster. Until you install this hook, builds will hang waiting for
a response from CircleCI.

```lang=yml
notify:
  webhooks:
    - url: %s
```

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

  public static function getGitHubPath($uri) {
    $uri_object = new PhutilURI($uri);
    $domain = $uri_object->getDomain();

    $domain = phutil_utf8_strtolower($domain);
    switch ($domain) {
      case 'github.com':
      case 'www.github.com':
        return $uri_object->getPath();
      default:
        return null;
    }
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $buildable = $build->getBuildable();

    $object = $buildable->getBuildableObject();
    $object_phid = $object->getPHID();
    if (!($object instanceof HarbormasterCircleCIBuildableInterface)) {
      throw new Exception(
        pht(
          'Object ("%s") does not implement interface "%s". Only objects '.
          'which implement this interface can be built with CircleCI.',
          $object_phid,
          'HarbormasterCircleCIBuildableInterface'));
    }

    $github_uri = $object->getCircleCIGitHubRepositoryURI();
    $build_type = $object->getCircleCIBuildIdentifierType();
    $build_identifier = $object->getCircleCIBuildIdentifier();

    $path = self::getGitHubPath($github_uri);
    if ($path === null) {
      throw new Exception(
        pht(
          'Object ("%s") claims "%s" is a GitHub repository URI, but the '.
          'domain does not appear to be GitHub.',
          $object_phid,
          $github_uri));
    }

    $path_parts = trim($path, '/');
    $path_parts = explode('/', $path_parts);
    if (count($path_parts) < 2) {
      throw new Exception(
        pht(
          'Object ("%s") claims "%s" is a GitHub repository URI, but the '.
          'path ("%s") does not have enough components (expected at least '.
          'two).',
          $object_phid,
          $github_uri,
          $path));
    }

    list($github_namespace, $github_name) = $path_parts;
    $github_name = preg_replace('(\\.git$)', '', $github_name);

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

    // When we pass "revision", the branch is ignored (and does not even need
    // to exist), and only shows up in the UI. Use a cute string which will
    // certainly never break anything or cause any kind of problem.
    $ship = "\xF0\x9F\x9A\xA2";
    $branch = "{$ship}Harbormaster";

    $token = $api_token->getSecret()->openEnvelope();
    $parts = array(
      'https://circleci.com/api/v1/project',
      phutil_escape_uri($github_namespace),
      phutil_escape_uri($github_name)."?circle-token={$token}",
    );

    $uri = implode('/', $parts);

    $data_structure = array();
    switch ($build_type) {
      case 'tag':
        $data_structure['tag'] = $build_identifier;
        break;
      case 'revision':
        $data_structure['revision'] = $build_identifier;
        break;
      default:
        throw new Exception(
          pht(
            'Unknown CircleCI build type "%s". Expected "%s" or "%s".',
            $build_type,
            'tag',
            'revision'));
    }

    $data_structure['build_parameters'] = array(
      'HARBORMASTER_BUILD_TARGET_PHID' => $build_target->getPHID(),
    );

    $json_data = phutil_json_encode($data_structure);

    $future = id(new HTTPSFuture($uri, $json_data))
      ->setMethod('POST')
      ->addHeader('Content-Type', 'application/json')
      ->addHeader('Accept', 'application/json')
      ->setTimeout(60);

    $this->resolveFutures(
      $build,
      $build_target,
      array($future));

    $this->logHTTPResponse($build, $build_target, $future, pht('CircleCI'));

    list($status, $body) = $future->resolve();
    if ($status->isError()) {
      throw new HarbormasterBuildFailureException();
    }

    $response = phutil_json_decode($body);
    $build_uri = idx($response, 'build_url');
    if (!$build_uri) {
      throw new Exception(
        pht(
          'CircleCI did not return a "%s"!',
          'build_url'));
    }

    $target_phid = $build_target->getPHID();

    // Write an artifact to create a link to the external build in CircleCI.

    $api_method = 'harbormaster.createartifact';
    $api_params = array(
      'buildTargetPHID' => $target_phid,
      'artifactType' => HarbormasterURIArtifact::ARTIFACTCONST,
      'artifactKey' => 'circleci.uri',
      'artifactData' => array(
        'uri' => $build_uri,
        'name' => pht('View in CircleCI'),
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
    );
  }

  public function supportsWaitForMessage() {
    // NOTE: We always wait for a message, but don't need to show the UI
    // control since "Wait" is the only valid choice.
    return false;
  }

  public function shouldWaitForMessage(HarbormasterBuildTarget $target) {
    return true;
  }

}
