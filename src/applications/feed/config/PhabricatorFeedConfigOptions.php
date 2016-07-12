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
    return array(
      $this->newOption('feed.http-hooks', 'list<string>', array())
        ->setLocked(true)
        ->setSummary(pht('POST notifications of feed events.'))
        ->setDescription(
          pht(
            "If you set this to a list of HTTP URIs, when a feed story is ".
            "published a task will be created for each URI that posts the ".
            "story data to the URI. Daemons automagically retry failures 100 ".
            "times, waiting `\$fail_count * 60s` between each subsequent ".
            "failure. Be sure to keep the daemon console (`%s`) open ".
            "while developing and testing your end points. You may need to".
            "restart your daemons to start sending HTTP requests.\n\n".
            "NOTE: URIs are not validated, the URI must return HTTP status ".
            "200 within 30 seconds, and no permission checks are performed.",
            '/daemon/')),
    );
  }

}
