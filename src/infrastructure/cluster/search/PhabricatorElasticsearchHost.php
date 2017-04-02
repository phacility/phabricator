<?php

final class PhabricatorElasticsearchHost
  extends PhabricatorSearchHost {

  private $version = 5;
  private $path = 'phabricator/';
  private $protocol = 'http';

  const KEY_REFS = 'search.elastic.refs';


  public function setConfig($config) {
    $this->setRoles(idx($config, 'roles', $this->getRoles()))
      ->setHost(idx($config, 'host', $this->host))
      ->setPort(idx($config, 'port', $this->port))
      ->setProtocol(idx($config, 'protocol', $this->protocol))
      ->setPath(idx($config, 'path', $this->path))
      ->setVersion(idx($config, 'version', $this->version));
    return $this;
  }

  public function getDisplayName() {
    return pht('Elasticsearch');
  }

  public function getStatusViewColumns() {
    return array(
        pht('Protocol') => $this->getProtocol(),
        pht('Host') => $this->getHost(),
        pht('Port') => $this->getPort(),
        pht('Index Path') => $this->getPath(),
        pht('Elastic Version') => $this->getVersion(),
        pht('Roles') => implode(', ', array_keys($this->getRoles())),
    );
  }

  public function setProtocol($protocol) {
    $this->protocol = $protocol;
    return $this;
  }

  public function getProtocol() {
    return $this->protocol;
  }

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getPath() {
    return $this->path;
  }

  public function setVersion($version) {
    $this->version = $version;
    return $this;
  }

  public function getVersion() {
    return $this->version;
  }

  public function getURI($to_path = null) {
    $uri = id(new PhutilURI('http://'.$this->getHost()))
      ->setProtocol($this->getProtocol())
      ->setPort($this->getPort())
      ->setPath($this->getPath());

    if ($to_path) {
      $uri->appendPath($to_path);
    }
    return $uri;
  }

  public function getConnectionStatus() {
    $status = $this->getEngine()->indexIsSane($this);
    return $status ? parent::STATUS_OKAY : parent::STATUS_FAIL;
  }

}
