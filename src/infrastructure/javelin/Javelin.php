<?php

final class Javelin {

  public static function initBehavior($behavior, array $config = array()) {
    switch ($behavior) {
      case 'differential-dropdown-menus':
        $config['pht'] = array(
          'Open in Editor' => pht('Open in Editor'),
          'Show Entire File' => pht('Show Entire File'),
          'Entire File Shown' => pht('Entire File Shown'),
          "Can't Toggle Unloaded File" => pht("Can't Toggle Unloaded File"),
          'Expand File' => pht('Expand File'),
          'Collapse File' => pht('Collapse File'),
          'Browse in Diffusion' => pht('Browse in Diffusion'),
          'View Standalone' => pht('View Standalone'),
          'Show Raw File (Left)' => pht('Show Raw File (Left)'),
          'Show Raw File (Right)' => pht('Show Raw File (Right)'),
          'Configure Editor' => pht('Configure Editor'),
        );
        break;
    }

    $response = CelerityAPI::getStaticResourceResponse();
    $response->initBehavior($behavior, $config);
  }

}
