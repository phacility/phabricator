<?php


class BugStore {
  /** @var array<string, string> */
  private array $cache;

  public function __construct() {
    $this->cache = [];
  }

  public function resolveBug(DifferentialRevision $rawRevision): ?SecureEmailBug {
    $bugzillaField = new DifferentialBugzillaBugIDField();
    $bugzillaField->setObject($rawRevision);
    (new PhabricatorCustomFieldStorageQuery())
      ->addField($bugzillaField)
      ->execute();
    if (!$bugzillaField->getValue()) {
      return null;
    } else {
      $id = intval($bugzillaField->getValue());
      $link = (string) (new PhutilURI(PhabricatorEnv::getEnvConfig('bugzilla.url')))
        ->setPath('/show_bug.cgi')
        ->appendQueryParam('id', $id);
      return new SecureEmailBug($id, $link);
    }
  }

  public function queryName(string $id) {
    if (array_key_exists($id, $this->cache)) {
      return $this->cache[$id];
    }

    $bugApiURI = (new PhutilURI(PhabricatorEnv::getEnvConfig('bugzilla.url')))
      ->setPath('/rest/bug/' . $id)
      ->appendQueryParam('include_fields', 'summary')
      ->appendQueryParam('api_key', PhabricatorEnv::getEnvConfig('bugzilla.automation_api_key'));

    try {
      $rawBugResponse = file_get_contents((string)$bugApiURI);
    } catch (RuntimeException $e) {
      // If the request fails (or 404s), Phabricator catches the error and throws a RuntimeException
      return null;
    }

    $jsonBugResponse = json_decode($rawBugResponse);
    $bugName = current($jsonBugResponse->bugs)->summary;
    $this->cache[$id] = $bugName;
    return $bugName;
  }
}