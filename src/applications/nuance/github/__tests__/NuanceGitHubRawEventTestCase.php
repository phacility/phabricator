<?php

final class NuanceGitHubRawEventTestCase
  extends PhabricatorTestCase {

  public function testIssueEvents() {
    $path = dirname(__FILE__).'/issueevents/';

    $cases = $this->readTestCases($path);

    foreach ($cases as $name => $info) {
      $input = $info['input'];
      $expect = $info['expect'];

      $event = NuanceGitHubRawEvent::newEvent(
        NuanceGitHubRawEvent::TYPE_ISSUE,
        $input);

      $this->assertGitHubRawEventParse($expect, $event, $name);
    }
  }

  public function testRepositoryEvents() {
    $path = dirname(__FILE__).'/repositoryevents/';

    $cases = $this->readTestCases($path);

    foreach ($cases as $name => $info) {
      $input = $info['input'];
      $expect = $info['expect'];

      $event = NuanceGitHubRawEvent::newEvent(
        NuanceGitHubRawEvent::TYPE_REPOSITORY,
        $input);

      $this->assertGitHubRawEventParse($expect, $event, $name);
    }
  }

  private function assertGitHubRawEventParse(
    array $expect,
    NuanceGitHubRawEvent $event,
    $name) {

    $actual = array(
      'repository.name.full' => $event->getRepositoryFullName(),
      'is.issue' => $event->isIssueEvent(),
      'is.pull' => $event->isPullRequestEvent(),
      'issue.number' => $event->getIssueNumber(),
      'pull.number' => $event->getPullRequestNumber(),
      'id' => $event->getID(),
      'uri' => $event->getURI(),
      'title.full' => $event->getEventFullTitle(),
      'comment' => $event->getComment(),
      'actor.id' => $event->getActorGitHubUserID(),
    );

    // Only verify the keys which are actually present in the test. This
    // allows tests to specify only relevant keys.
    $actual = array_select_keys($actual, array_keys($expect));

    ksort($expect);
    ksort($actual);

    $this->assertEqual($expect, $actual, $name);
  }

  private function readTestCases($path) {
    $files = Filesystem::listDirectory($path, $include_hidden = false);

    $tests = array();
    foreach ($files as $file) {
      $data = Filesystem::readFile($path.$file);

      $parts = preg_split('/^~{5,}$/m', $data);
      if (count($parts) < 2) {
        throw new Exception(
          pht(
            'Expected test file "%s" to contain an input section in JSON, '.
            'then an expected result section in JSON, with the two sections '.
            'separated by a line of "~~~~~", but the divider is not present '.
            'in the file.',
            $file));
      } else if (count($parts) > 2) {
        throw new Exception(
          pht(
            'Expected test file "%s" to contain exactly two sections, '.
            'but it has more than two sections.',
            $file));
      }

      list($input, $expect) = $parts;

      try {
        $input = phutil_json_decode($input);
        $expect = phutil_json_decode($expect);
      } catch (Exception $ex) {
        throw new PhutilProxyException(
          pht(
            'Exception while decoding test data for test "%s".',
            $file),
          $ex);
      }

      $tests[$file] = array(
        'input' => $input,
        'expect' => $expect,
      );
    }

    return $tests;
  }

}
