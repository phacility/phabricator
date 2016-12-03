<?php

final class PhabricatorManualActivitySetupCheck
  extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  protected function executeChecks() {
    $activities = id(new PhabricatorConfigManualActivity())->loadAll();

    foreach ($activities as $activity) {
      $type = $activity->getActivityType();

      // For now, there is only one type of manual activity. It's not clear
      // if we're really going to have too much more of this stuff so this
      // is a bit under-designed for now.

      $activity_name = pht('Rebuild Search Index');
      $activity_summary = pht(
        'The search index algorithm has been updated and the index needs '.
        'be rebuilt.');

      $message = array();

      $message[] = pht(
        'The indexing algorithm for the fulltext search index has been '.
        'updated and the index needs to be rebuilt. Until you rebuild the '.
        'index, global search (and other fulltext search) will not '.
        'function correctly.');

      $message[] = pht(
        'You can rebuild the search index while Phabricator is running.');

      $message[] = pht(
        'To rebuild the index, run this command:');

      $message[] = phutil_tag(
        'pre',
        array(),
        (string)csprintf(
          'phabricator/ $ ./bin/search index --all --force --background'));

      $message[] = pht(
        'You can find more information about rebuilding the search '.
        'index here: %s',
        phutil_tag(
          'a',
          array(
            'href' => 'https://phurl.io/u/reindex',
            'target' => '_blank',
          ),
          'https://phurl.io/u/reindex'));

      $message[] = pht(
        'After rebuilding the index, run this command to clear this setup '.
        'warning:');

      $message[] = phutil_tag(
        'pre',
        array(),
        (string)csprintf('phabricator/ $ ./bin/config done %R', $type));

      $activity_message = phutil_implode_html("\n\n", $message);

      $this->newIssue('manual.'.$type)
        ->setName($activity_name)
        ->setSummary($activity_summary)
        ->setMessage($activity_message);
    }

  }

}
