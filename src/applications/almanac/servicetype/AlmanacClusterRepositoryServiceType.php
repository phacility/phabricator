<?php

final class AlmanacClusterRepositoryServiceType
  extends AlmanacClusterServiceType {

  const SERVICETYPE = 'cluster.repository';

  public function getServiceTypeShortName() {
    return pht('Cluster Repository');
  }

  public function getServiceTypeName() {
    return pht('Cluster: Repository');
  }

  public function getServiceTypeDescription() {
    return pht(
      'Defines a repository service for use in a cluster.');
  }

  public function getFieldSpecifications() {
    return array(
      'closed' => id(new PhabricatorBoolEditField())
        ->setOptions(
          pht('Allow New Repositories'),
          pht('Prevent New Repositories'))
        ->setValue(false),
    );
  }

  public function getBindingFieldSpecifications(AlmanacBinding $binding) {
    $protocols = array(
      array(
        'value' => 'http',
        'port' => 80,
      ),
      array(
        'value' => 'https',
        'port' => 443,
      ),
      array(
        'value' => 'ssh',
        'port' => 22,
      ),
    );

    $default_value = 'http';
    if ($binding->hasInterface()) {
      $interface = $binding->getInterface();
      $port = $interface->getPort();

      $default_ports = ipull($protocols, 'value', 'port');
      $default_value = idx($default_ports, $port, $default_value);
    }

    return array(
      'protocol' => id(new PhabricatorSelectEditField())
        ->setOptions(ipull($protocols, 'value', 'value'))
        ->setValue($default_value),
      'writable' => id(new PhabricatorBoolEditField())
        ->setOptions(
          pht('Prevent Writes'),
          pht('Allow Writes'))
        ->setValue(true),
    );
  }

}
