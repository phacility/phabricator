<?php

final class PhabricatorFeedConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Feed');
  }

  public function getDescription() {
    return pht('Feed options.');
  }

  public function getIcon() {
    return 'fa-newspaper-o';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    $hooks_help = $this->deformat(pht(<<<EODOC
IMPORTANT: Feed hooks are deprecated and have been replaced by Webhooks.

You can configure Webhooks in Herald. This configuration option will be removed
in a future version of the software.

(This legacy option may be configured with a list of URIs; feed stories will
send to these URIs.)
EODOC
      ));

    return array(
      $this->newOption('feed.http-hooks', 'list<string>', array())
        ->setLocked(true)
        ->setSummary(pht('Deprecated.'))
        ->setDescription($hooks_help),
    );
  }

}
