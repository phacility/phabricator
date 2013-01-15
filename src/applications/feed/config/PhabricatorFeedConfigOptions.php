<?php

final class PhabricatorFeedConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Feed');
  }

  public function getDescription() {
    return pht('Feed options.');
  }

  public function getOptions() {
    return array(
      $this->newOption('feed.public', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Allow anyone to view the feed'),
            pht('Require authentication'),
          ))
        ->setSummary(pht('Should the feed be public?'))
        ->setDescription(
          pht(
            "If you set this to true, you can embed Phabricator activity ".
            "feeds in other pages using iframes. These feeds are completely ".
            "public, and a login is not required to view them! This is ".
            "intended for things like open source projects that want to ".
            "expose an activity feed on the project homepage.\n\n".
            "NOTE: You must also set `policy.allow-public` to true for this ".
            "setting to work properly.")),
      $this->newOption('feed.http-hooks', 'list<string>', array())
        ->setSummary(pht('POST notifications of feed events.'))
        ->setDescription(
          pht(
            "If you set this to a list of http URIs, when a feed story is ".
            "published a task will be created for each uri that posts the ".
            "story data to the uri. Daemons automagically retry failures 100 ".
            "times, waiting \$fail_count * 60s between each subsequent ".
            "failure. Be sure to keep the daemon console (/daemon/) open ".
            "while developing and testing your end points.\n\n".
            "NOTE: URIs are not validated, the URI must return http status ".
            "200 within 30 seconds, and no permission checks are performed.")),
    );
  }

}
