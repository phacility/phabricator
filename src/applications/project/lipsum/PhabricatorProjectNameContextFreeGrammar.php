<?php

final class PhabricatorProjectNameContextFreeGrammar
  extends PhutilContextFreeGrammar {

  protected function getRules() {
    return array(
      'start' => array(
        '[project]',
        '[project] [tion]',
        '[action] [project]',
        '[action] [project] [tion]',
      ),
      'project' => array(
        'Backend',
        'Frontend',
        'Web',
        'Mobile',
        'Tablet',
        'Robot',
        'NUX',
        'Cars',
        'Drones',
        'Experience',
        'Swag',
        'Security',
        'Culture',
        'Revenue',
        'Ion Cannon',
        'Graphics Engine',
        'Drivers',
        'Audio Drivers',
        'Graphics Drivers',
        'Hardware',
        'Data Center',
        '[project] [project]',
        '[adjective] [project]',
        '[adjective] [project]',
      ),
      'adjective' => array(
        'Self-Driving',
        'Self-Flying',
        'Self-Immolating',
        'Secure',
        'Insecure',
        'Somewhat-Secure',
        'Orbital',
        'Next-Generation',
      ),
      'tion' => array(
        'Automation',
        'Optimization',
        'Performance',
        'Improvement',
        'Growth',
        'Monetization',
      ),
      'action' => array(
        'Monetize',
        'Monetize',
        'Triage',
        'Triaging',
        'Automate',
        'Automating',
        'Improve',
        'Improving',
        'Optimize',
        'Optimizing',
        'Accelerate',
        'Accelerating',
      ),
    );
  }

}
