<?php

/**
 * Simple convenience log type for logging arbitrary text.
 *
 * Drydock logs can be given formal types, which allows them to be translated
 * and filtered. If you don't particularly care about fancy logging features,
 * you can use this log type to just dump some text into the log. Maybe you
 * could upgrade to more formal logging later.
 */
final class DrydockTextLogType extends DrydockLogType {

  const LOGCONST = 'core.text';

  public function getLogTypeName() {
    return pht('Text');
  }

  public function getLogTypeIcon(array $data) {
    return 'fa-file-text-o grey';
  }

  public function renderLog(array $data) {
    return idx($data, 'text');
  }

}
