<?php

final class HarbormasterURIArtifact extends HarbormasterArtifact {

  const ARTIFACTCONST = 'uri';

  public function getArtifactTypeName() {
    return pht('URI');
  }

  public function getArtifactTypeSummary() {
    return pht('Stores a URI.');
  }

  public function getArtifactTypeDescription() {
    return pht(
      "Stores a URI.\n\n".
      "With `ui.external`, you can use this artifact type to add links to ".
      "build results in an external build system.");
  }

  public function getArtifactParameterSpecification() {
    return array(
      'uri' => 'string',
      'name' => 'optional string',
      'ui.external' => 'optional bool',
    );
  }

  public function getArtifactParameterDescriptions() {
    return array(
      'uri' => pht('The URI to store.'),
      'name' => pht('Optional label for this URI.'),
      'ui.external' => pht(
        'If true, display this URI in the UI as an link to '.
        'additional build details in an external build system.'),
    );
  }

  public function getArtifactDataExample() {
    return array(
      'uri' => 'https://buildserver.mycompany.com/build/123/',
      'name' => pht('View External Build Results'),
      'ui.external' => true,
    );
  }

  public function renderArtifactSummary(PhabricatorUser $viewer) {
    return $this->renderLink();
  }

  public function isExternalLink() {
    $artifact = $this->getBuildArtifact();
    return (bool)$artifact->getProperty('ui.external', false);
  }

  public function renderLink() {
    $artifact = $this->getBuildArtifact();
    $uri = $artifact->getProperty('uri');

    try {
      $this->validateURI($uri);
    } catch (Exception $ex) {
      return pht('<Invalid URI>');
    }

    $name = $artifact->getProperty('name', $uri);

    return phutil_tag(
      'a',
      array(
        'href' => $uri,
        'target' => '_blank',
      ),
      $name);
  }

  public function willCreateArtifact(PhabricatorUser $actor) {
    $artifact = $this->getBuildArtifact();
    $uri = $artifact->getProperty('uri');
    $this->validateURI($uri);
  }

  private function validateURI($raw_uri) {
    $uri = new PhutilURI($raw_uri);

    $protocol = $uri->getProtocol();
    if (!strlen($protocol)) {
      throw new Exception(
        pht(
          'Unable to identify the protocol for URI "%s". URIs must be '.
          'fully qualified and have an identifiable protocol.',
          $raw_uri));
    }

    $protocol_key = 'uri.allowed-protocols';
    $protocols = PhabricatorEnv::getEnvConfig($protocol_key);
    if (empty($protocols[$protocol])) {
      throw new Exception(
        pht(
          'URI "%s" does not have an allowable protocol. Configure '.
          'protocols in `%s`. Allowed protocols are: %s.',
          $raw_uri,
          $protocol_key,
          implode(', ', array_keys($protocols))));
    }
  }

}
