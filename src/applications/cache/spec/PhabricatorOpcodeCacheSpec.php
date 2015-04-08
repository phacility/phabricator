<?php

final class PhabricatorOpcodeCacheSpec extends PhabricatorCacheSpec {

  public static function getActiveCacheSpec() {
    $spec = new PhabricatorOpcodeCacheSpec();

    // NOTE: If APCu is installed, it reports that APC is installed.
    if (extension_loaded('apc') && !extension_loaded('apcu')) {
      $spec->initAPCSpec();
    } else if (extension_loaded('Zend OPcache')) {
      $spec->initOpcacheSpec();
    } else {
      $spec->initNoneSpec();
    }

    return $spec;
  }

  private function initAPCSpec() {
    $this
      ->setName(pht('APC'))
      ->setVersion(phpversion('apc'));

    if (ini_get('apc.enabled')) {
      $this->setIsEnabled(true);

      $mem = apc_sma_info();
      $this->setTotalMemory($mem['num_seg'] * $mem['seg_size']);

      $info = apc_cache_info();
      $this->setUsedMemory($info['mem_size']);

      $write_lock = ini_get('apc.write_lock');
      $slam_defense = ini_get('apc.slam_defense');

      if (!$write_lock || $slam_defense) {
        $summary = pht(
          'Adjust APC settings to quiet unnecessary errors.');

        $message = pht(
          'Some versions of APC may emit unnecessary errors into the '.
          'error log under the current APC settings. To resolve this, '.
          'enable "apc.write_lock" and disable "apc.slam_defense" in '.
          'your PHP configuration.');

        $this
          ->newIssue('extension.apc.write-lock')
          ->setShortName(pht('Noisy APC'))
          ->setName(pht('APC Has Noisy Configuration'))
          ->setSummary($summary)
          ->setMessage($message)
          ->addPHPConfig('apc.write_lock')
          ->addPHPConfig('apc.slam_defense');
      }

      $is_dev = PhabricatorEnv::getEnvConfig('phabricator.developer-mode');
      $is_stat_enabled = ini_get('apc.stat');
      if ($is_stat_enabled && !$is_dev) {
        $summary = pht(
          '"apc.stat" is currently enabled, but should probably be disabled.');

        $message = pht(
          'The "apc.stat" setting is currently enabled in your PHP '.
          'configuration. In production mode, "apc.stat" should be '.
          'disabled. This will improve performance slightly.');

        $this
          ->newIssue('extension.apc.stat-enabled')
          ->setShortName(pht('"apc.stat" Enabled'))
          ->setName(pht('"apc.stat" Enabled in Production'))
          ->setSummary($summary)
          ->setMessage($message)
          ->addPHPConfig('apc.stat')
          ->addPhabricatorConfig('phabricator.developer-mode');
      } else if (!$is_stat_enabled && $is_dev) {
        $summary = pht(
          '"apc.stat" is currently disabled, but should probably be enabled.');

        $message = pht(
          'The "apc.stat" setting is currently disabled in your PHP '.
          'configuration, but Phabricator is running in development mode. '.
          'This option should normally be enabled in development so you do '.
          'not need to restart your webserver after making changes to the '.
          'code.');

        $this
          ->newIssue('extension.apc.stat-disabled')
          ->setShortName(pht('"apc.stat" Disabled'))
          ->setName(pht('"apc.stat" Disabled in Development'))
          ->setSummary($summary)
          ->setMessage($message)
          ->addPHPConfig('apc.stat')
          ->addPhabricatorConfig('phabricator.developer-mode');
      }
    } else {
      $this->setIsEnabled(false);
      $this->raiseEnableAPCIssue();
    }
  }

  private function initOpcacheSpec() {
    $this
      ->setName(pht('Zend OPcache'))
      ->setVersion(phpversion('Zend OPcache'));

    if (ini_get('opcache.enable')) {
      $this->setIsEnabled(true);

      $status = opcache_get_status();
      $memory = $status['memory_usage'];

      $mem_used = $memory['used_memory'];
      $mem_free = $memory['free_memory'];
      $mem_junk = $memory['wasted_memory'];
      $this->setUsedMemory($mem_used + $mem_junk);
      $this->setTotalMemory($mem_used + $mem_junk + $mem_free);
      $this->setEntryCount($status['opcache_statistics']['num_cached_keys']);

      $is_dev = PhabricatorEnv::getEnvConfig('phabricator.developer-mode');

      $validate = ini_get('opcache.validate_timestamps');
      $freq = ini_get('opcache.revalidate_freq');
      if ($is_dev && (!$validate || $freq)) {
        $summary = pht(
          'OPcache is not configured properly for development.');

        $message = pht(
          'In development, OPcache should be configured to always reload '.
          'code so the webserver does not need to be restarted after making '.
          'changes. To do this, enable "opcache.validate_timestamps" and '.
          'set "opcache.revalidate_freq" to 0.');

        $this
          ->newIssue('extension.opcache.devmode')
          ->setShortName(pht('OPcache Config'))
          ->setName(pht('OPCache Not Configured for Development'))
          ->setSummary($summary)
          ->setMessage($message)
          ->addPHPConfig('opcache.validate_timestamps')
          ->addPHPConfig('opcache.revalidate_freq')
          ->addPhabricatorConfig('phabricator.developer-mode');
      } else if (!$is_dev && $validate) {
        $summary = pht(
          'OPcache is not configured ideally for production.');

        $message = pht(
          'In production, OPcache should be configured to never '.
          'revalidate code. This will slightly improve performance. '.
          'To do this, disable "opcache.validate_timestamps" in your PHP '.
          'configuration.');

        $this
          ->newIssue('extension.opcache.production')
          ->setShortName(pht('OPcache Config'))
          ->setName(pht('OPcache Not Configured for Production'))
          ->setSummary($summary)
          ->setMessage($message)
          ->addPHPConfig('opcache.validate_timestamps')
          ->addPhabricatorConfig('phabricator.developer-mode');
      }
    } else {
      $this->setIsEnabled(false);

      $summary = pht('Enabling OPcache will dramatically improve performance.');
      $message = pht(
        'The PHP "Zend OPcache" extension is installed, but not enabled in '.
        'your PHP configuration. Enabling it will dramatically improve '.
        'Phabricator performance. Edit the "opcache.enable" setting to '.
        'enable the extension.');

      $this->newIssue('extension.opcache.enable')
        ->setShortName(pht('OPcache Disabled'))
        ->setName(pht('Zend OPcache Not Enabled'))
        ->setSummary($summary)
        ->setMessage($message)
        ->addPHPConfig('opcache.enable');
    }
  }

  private function initNoneSpec() {
    if (version_compare(phpversion(), '5.5', '>=')) {
      $message = pht(
        'Installing the "Zend OPcache" extension will dramatically improve '.
        'performance.');

      $this
        ->newIssue('extension.opcache')
        ->setShortName(pht('OPcache'))
        ->setName(pht('Zend OPcache Not Installed'))
        ->setMessage($message)
        ->addPHPExtension('Zend OPcache');
    } else {
      $this->raiseInstallAPCIssue();
    }
  }

}
