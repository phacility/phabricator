<?php

final class PhabricatorOwnersPackageContextFreeGrammar
  extends PhutilContextFreeGrammar {

  protected function getRules() {
    return array(
      'start' => array(
        '[package]',
      ),
      'package' => array(
        '[adjective] [noun]',
        '[adjective] [noun]',
        '[adjective] [noun]',
        '[adjective] [noun]',
        '[adjective] [adjective] [noun]',
        '[adjective] [noun] [noun]',
        '[adjective] [adjective] [noun] [noun]',
      ),
      'adjective' => array(
        'Temporary',
        'Backend',
        'External',
        'Emergency',
        'Applied',
        'Advanced',
        'Experimental',
        'Logging',
        'Test',
        'Network',
        'Ephemeral',
        'Clustered',
        'Mining',
        'Core',
        'Remote',
      ),
      'noun' => array(
        'Support',
        'Services',
        'Infrastructure',
        'Mail',
        'Security',
        'Application',
        'Microservices',
        'Monoservices',
        'Megaservices',
        'API',
        'Storage',
        'Records',
        'Package',
        'Directories',
        'Library',
        'Concern',
        'Cluster',
        'Engine',
      ),
    );
  }

}
