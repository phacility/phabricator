<?php

/**
 * Overseer module.
 *
 * The primary purpose of this overseer module is to poll for configuration
 * changes and reload daemons when the configuration changes.
 */
final class PhabricatorDaemonOverseerModule
  extends PhutilDaemonOverseerModule {

  private $configVersion;
  private $timestamp;

  public function __construct() {
    $this->timestamp = PhabricatorTime::getNow();
  }

  public function shouldReloadDaemons() {
    $now = PhabricatorTime::getNow();
    $ago = ($now - $this->timestamp);

    // Don't check more than once every 10 seconds.
    if ($ago < 10) {
      return false;
    }

    return $this->updateConfigVersion();
  }

  /**
   * Calculate a version number for the current Phabricator configuration.
   *
   * The version number has no real meaning and does not provide any real
   * indication of whether a configuration entry has been changed. The config
   * version is intended to be a rough indicator that "something has changed",
   * which indicates to the overseer that the daemons should be reloaded.
   *
   * @return int
   */
  private function loadConfigVersion() {
    $conn_r = id(new PhabricatorConfigEntry())->establishConnection('r');
    return head(queryfx_one(
      $conn_r,
      'SELECT MAX(id) FROM %T',
      id(new PhabricatorConfigTransaction())->getTableName()));
  }

  /**
   * Update the configuration version and timestamp.
   *
   * @return bool  True if the daemons should restart, otherwise false.
   */
  private function updateConfigVersion() {
    $config_version = $this->loadConfigVersion();
    $this->timestamp = PhabricatorTime::getNow();

    if (!$this->configVersion) {
      $this->configVersion = $config_version;
      return false;
    }

    if ($this->configVersion != $config_version) {
      $this->configVersion = $config_version;
      return true;
    }

    return false;
  }

}
