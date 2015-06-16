<?php

/**
 * Configuration source which reads from a configuration file on disk (a
 * PHP file in the `conf/` directory).
 */
final class PhabricatorConfigFileSource extends PhabricatorConfigProxySource {

  /**
   * @phutil-external-symbol function phabricator_read_config_file
   */
  public function __construct($config) {
    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/conf/__init_conf__.php';

    $dictionary = phabricator_read_config_file($config);
    $dictionary['phabricator.env'] = $config;

    $this->setSource(new PhabricatorConfigDictionarySource($dictionary));
  }

}
