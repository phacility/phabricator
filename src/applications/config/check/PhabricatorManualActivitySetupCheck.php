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

      switch ($type) {
        case PhabricatorConfigManualActivity::TYPE_REINDEX:
          $this->raiseSearchReindexIssue();
          break;

        case PhabricatorConfigManualActivity::TYPE_IDENTITIES:
          $this->raiseRebuildIdentitiesIssue();
          break;

        default:
      }
    }
  }

  private function raiseSearchReindexIssue() {
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
      'You can rebuild the search index while the server is running.');

    $message[] = pht(
      'To rebuild the index, run this command:');

    $message[] = phutil_tag(
      'pre',
      array(),
      (string)csprintf(
        '$ ./bin/search index --all --force --background'));

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
      '$ ./bin/config done reindex');

    $activity_message = phutil_implode_html("\n\n", $message);

    $this->newIssue('manual.reindex')
      ->setName($activity_name)
      ->setSummary($activity_summary)
      ->setMessage($activity_message);
  }

  private function raiseRebuildIdentitiesIssue() {
    $activity_name = pht('Rebuild Repository Identities');
    $activity_summary = pht(
      'The mapping from VCS users to %s users has changed '.
      'and must be rebuilt.',
      PlatformSymbols::getPlatformServerName());

    $message = array();

    $message[] = pht(
      'The way VCS activity is attributed %s user accounts has changed.',
      PlatformSymbols::getPlatformServerName());

    $message[] = pht(
      'There is a new indirection layer between the strings that appear as '.
      'VCS authors and committers (such as "John Developer '.
      '<johnd@bigcorp.com>") and the user account that gets associated '.
      'with VCS commits.');

    $message[] = pht(
      'This change supports situations where users are incorrectly '.
      'associated with commits because the software makes a bad guess '.
      'about how the VCS string maps to a user account. '.
      'This also helps with situations where existing repositories are '.
      'imported without having created accounts for all the committers to '.
      'that repository. Until you rebuild these repository identities, you '.
      'are likely to encounter problems with features which rely on the '.
      'existence of these identities.');

    $message[] = pht(
      'You can rebuild repository identities while the server is running.');

    $message[] = pht(
      'To rebuild identities, run this command:');

    $message[] = phutil_tag(
      'pre',
      array(),
      (string)csprintf(
        '$ ./bin/repository rebuild-identities --all-repositories'));

    $message[] = pht(
      'You can find more information about this new identity mapping '.
      'here: %s',
      phutil_tag(
        'a',
        array(
          'href' => 'https://phurl.io/u/repoIdentities',
          'target' => '_blank',
        ),
        'https://phurl.io/u/repoIdentities'));

    $message[] = pht(
      'After rebuilding repository identities, run this command to clear '.
      'this setup warning:');

    $message[] = phutil_tag(
      'pre',
      array(),
      '$ ./bin/config done identities');

    $activity_message = phutil_implode_html("\n\n", $message);

    $this->newIssue('manual.identities')
      ->setName($activity_name)
      ->setSummary($activity_summary)
      ->setMessage($activity_message);
  }

}
