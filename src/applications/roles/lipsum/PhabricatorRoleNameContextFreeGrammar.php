<?php

final class PhabricatorRoleNameContextFreeGrammar
  extends PhutilContextFreeGrammar {

  protected function getRules() {
    return array(
      'start' => array(
        '[role]',
        '[role] [tion]',
        '[action] [role]',
        '[action] [role] [tion]',
      ),
      'role' => array(
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
        '[role] [role]',
        '[adjective] [role]',
        '[adjective] [role]',
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
