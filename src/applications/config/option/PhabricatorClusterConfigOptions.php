<?php

final class PhabricatorClusterConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Cluster Setup');
  }

  public function getDescription() {
    return pht('Configure Phabricator to run on a cluster of hosts.');
  }

  public function getOptions() {
    return array(
      $this->newOption('cluster.addresses', 'list<string>', array())
        ->setLocked(true)
        ->setSummary(pht('Address ranges of cluster hosts.'))
        ->setDescription(
          pht(
            'To allow Phabricator nodes to communicate with other nodes '.
            'in the cluster, provide an address whitelist of hosts that '.
            'are part of the cluster.'.
            "\n\n".
            'Hosts on this whitelist are permitted to use special cluster '.
            'mechanisms to authenticate requests. By default, these '.
            'mechanisms are disabled.'.
            "\n\n".
            'Define a list of CIDR blocks which whitelist all hosts in the '.
            'cluster. See the examples below for details.',
            "\n\n".
            'When cluster addresses are defined, Phabricator hosts will also '.
            'reject requests to interfaces which are not whitelisted.'))
        ->addExample(
          array(
            '23.24.25.80/32',
            '23.24.25.81/32',
          ),
          pht('Whitelist Specific Addresses'))
        ->addExample(
          array(
            '1.2.3.0/24',
          ),
          pht('Whitelist 1.2.3.*'))
        ->addExample(
          array(
            '1.2.0.0/16',
          ),
          pht('Whitelist 1.2.*.*'))
        ->addExample(
          array(
            '0.0.0.0/0',
          ),
          pht('Allow Any Host (Insecure!)')),
    );
  }

}
