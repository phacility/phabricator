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
      $this
        ->setIsEnabled(true)
        ->setClearCacheCallback('apc_clear_cache');

      $mem = apc_sma_info();
      $this->setTotalMemory($mem['num_seg'] * $mem['seg_size']);

      $info = apc_cache_info();
      $this->setUsedMemory($info['mem_size']);

      $write_lock = ini_get('apc.write_lock');
      $slam_defense = ini_get('apc.slam_defense');

      if (!$write_lock || $slam_defense) {
        $summary = pht('Adjust APC settings to quiet unnecessary errors.');

        $message = pht(
          'Some versions of APC may emit unnecessary errors into the '.
          'error log under the current APC settings. To resolve this, '.
          'enable "%s" and disable "%s" in your PHP configuration.',
          'apc.write_lock',
          'apc.slam_defense');

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
          '"%s" is currently enabled, but should probably be disabled.',
          'apc.stat');

        $message = pht(
          'The "%s" setting is currently enabled in your PHP configuration. '.
          'In production mode, "%s" should be disabled. '.
          'This will improve performance slightly.',
          'apc.stat',
          'apc.stat');

        $this
          ->newIssue('extension.apc.stat-enabled')
          ->setShortName(pht('"%s" Enabled', 'apc.stat'))
          ->setName(pht('"%s" Enabled in Production', 'apc.stat'))
          ->setSummary($summary)
          ->setMessage($message)
          ->addPHPConfig('apc.stat')
          ->addPhabricatorConfig('phabricator.developer-mode');
      } else if (!$is_stat_enabled && $is_dev) {
        $summary = pht(
          '"%s" is currently disabled, but should probably be enabled.',
          'apc.stat');

        $message = pht(
          'The "%s" setting is currently disabled in your PHP configuration, '.
          'but this software is running in development mode. This option '.
          'should normally be enabled in development so you do not need to '.
          'restart anything after making changes to the code.',
          'apc.stat');

        $this
          ->newIssue('extension.apc.stat-disabled')
          ->setShortName(pht('"%s" Disabled', 'apc.stat'))
          ->setName(pht('"%s" Disabled in Development', 'apc.stat'))
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
      $this
        ->setIsEnabled(true)
        ->setClearCacheCallback('opcache_reset');

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
          'code so nothing needs to be restarted after making changes. To do '.
          'this, enable "%s" and set "%s" to 0.',
          'opcache.validate_timestamps',
          'opcache.revalidate_freq');

        $this
          ->newIssue('extension.opcache.devmode')
          ->setShortName(pht('OPcache Config'))
          ->setName(pht('OPcache Not Configured for Development'))
          ->setSummary($summary)
          ->setMessage($message)
          ->addPHPConfig('opcache.validate_timestamps')
          ->addPHPConfig('opcache.revalidate_freq')
          ->addPhabricatorConfig('phabricator.developer-mode');
      } else if (!$is_dev && $validate) {
        $summary = pht('OPcache is not configured ideally for production.');

        $message = pht(
          'In production, OPcache should be configured to never '.
          'revalidate code. This will slightly improve performance. '.
          'To do this, disable "%s" in your PHP configuration.',
          'opcache.validate_timestamps');

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
        'performance. Edit the "%s" setting to enable the extension.',
        'opcache.enable');

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
