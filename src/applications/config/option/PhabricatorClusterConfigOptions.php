<?php

final class PhabricatorClusterConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Cluster Setup');
  }

  public function getDescription() {
    return pht('Configure services to run on a cluster of hosts.');
  }

  public function getIcon() {
    return 'fa-sitemap';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    $databases_type = 'cluster.databases';
    $databases_help = $this->deformat(pht(<<<EOTEXT
WARNING: This is a prototype option and the description below is currently pure
fantasy.

This option allows you to make this service aware of database read replicas so
it can monitor database health, spread load, and degrade gracefully to
read-only mode in the event of a failure on the primary host. For help with
configuring cluster databases, see **[[ %s | %s ]]** in the documentation.
EOTEXT
      ,
      PhabricatorEnv::getDoclink('Cluster: Databases'),
      pht('Cluster: Databases')));


    $intro_href = PhabricatorEnv::getDoclink('Clustering Introduction');
    $intro_name = pht('Clustering Introduction');

    $search_type = 'cluster.search';
    $search_help = $this->deformat(pht(<<<EOTEXT
Define one or more fulltext storage services. Here you can configure which
hosts will handle fulltext search queries and indexing. For help with
configuring fulltext search clusters, see **[[ %s | %s ]]** in the
documentation.
EOTEXT
    ,
    PhabricatorEnv::getDoclink('Cluster: Search'),
    pht('Cluster: Search')));

    return array(
      $this->newOption('cluster.addresses', 'list<string>', array())
        ->setLocked(true)
        ->setSummary(pht('Address ranges of cluster hosts.'))
        ->setDescription(
          pht(
            'Define a cluster by providing a whitelist of host '.
            'addresses that are part of the cluster.'.
            "\n\n".
            'Hosts on this whitelist have special powers. These hosts are '.
            'permitted to bend security rules, and misconfiguring this list '.
            'can make your install less secure. For more information, '.
            'see **[[ %s | %s ]]**.'.
            "\n\n".
            'Define a list of CIDR blocks which whitelist all hosts in the '.
            'cluster and no additional hosts. See the examples below for '.
            'details.'.
            "\n\n".
            'When cluster addresses are defined, hosts will also '.
            'reject requests to interfaces which are not whitelisted.',
            $intro_href,
            $intro_name))
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
      $this->newOption('cluster.instance', 'string', null)
        ->setLocked(true)
        ->setSummary(pht('Instance identifier for multi-tenant clusters.'))
        ->setDescription(
          pht(
            'WARNING: This is a very advanced option, and only useful for '.
            'hosting providers running multi-tenant clusters.'.
            "\n\n".
            'If you provide an instance identifier here (normally by '.
            'injecting it with a `%s`), the server will pass it to '.
            'subprocesses and commit hooks in the `%s` environmental variable.',
            'PhabricatorConfigSiteSource',
            'PHABRICATOR_INSTANCE')),
      $this->newOption('cluster.read-only', 'bool', false)
        ->setLocked(true)
        ->setSummary(
          pht(
            'Activate read-only mode for maintenance or disaster recovery.'))
        ->setDescription(
          pht(
            'WARNING: This is a prototype option and the description below '.
            'is currently pure fantasy.'.
            "\n\n".
            'Switch the service to read-only mode. In this mode, users will '.
            'be unable to write new data. Normally, the cluster degrades '.
            'into this mode automatically when it detects that the database '.
            'master is unreachable, but you can activate it manually in '.
            'order to perform maintenance or test configuration.')),
      $this->newOption('cluster.databases', $databases_type, array())
        ->setHidden(true)
        ->setSummary(
          pht('Configure database read replicas.'))
        ->setDescription($databases_help),
      $this->newOption('cluster.search', $search_type, array())
        ->setLocked(true)
        ->setSummary(
          pht('Configure full-text search services.'))
        ->setDescription($search_help)
        ->setDefault(
          array(
            array(
              'type' => 'mysql',
              'roles' => array(
                'read' => true,
                'write' => true,
              ),
            ),
          )),
    );
  }

}
