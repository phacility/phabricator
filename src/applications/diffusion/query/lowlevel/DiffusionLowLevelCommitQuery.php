<?php

/**
 * Populate a @{class:DiffusionCommitRef} with information about a specific
 * commit in a repository. This is a low-level query which talks directly to
 * the underlying VCS.
 */
final class DiffusionLowLevelCommitQuery
  extends DiffusionLowLevelQuery {

  private $identifier;

  public function withIdentifier($identifier) {
    $this->identifier = $identifier;
    return $this;
  }

  protected function executeQuery() {
    if (!strlen($this->identifier)) {
      throw new PhutilInvalidStateException('withIdentifier');
    }

    $type = $this->getRepository()->getVersionControlSystem();
    switch ($type) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $result = $this->loadGitCommitRef();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $result = $this->loadMercurialCommitRef();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $result = $this->loadSubversionCommitRef();
        break;
      default:
        throw new Exception(pht('Unsupported repository type "%s"!', $type));
    }

    return $result;
  }

  private function loadGitCommitRef() {
    $repository = $this->getRepository();

    // NOTE: %B was introduced somewhat recently in git's history, so pull
    // commit message information with %s and %b instead.

    // Even though we pass --encoding here, git doesn't always succeed, so
    // we try a little harder, since git *does* tell us what the actual encoding
    // is correctly (unless it doesn't; encoding is sometimes empty).
    list($info) = $repository->execxLocalCommand(
      'log -n 1 --encoding=%s --format=%s %s --',
      'UTF-8',
      implode(
        '%x00',
        array('%e', '%cn', '%ce', '%an', '%ae', '%T', '%s%n%n%b')),
      $this->identifier);

    $parts = explode("\0", $info);
    $encoding = array_shift($parts);

    foreach ($parts as $key => $part) {
      if ($encoding) {
        $part = phutil_utf8_convert($part, 'UTF-8', $encoding);
      }
      $parts[$key] = phutil_utf8ize($part);
      if (!strlen($parts[$key])) {
        $parts[$key] = null;
      }
    }

    $hashes = array(
      id(new DiffusionCommitHash())
        ->setHashType(ArcanistDifferentialRevisionHash::HASH_GIT_COMMIT)
        ->setHashValue($this->identifier),
      id(new DiffusionCommitHash())
        ->setHashType(ArcanistDifferentialRevisionHash::HASH_GIT_TREE)
        ->setHashValue($parts[4]),
    );

    return id(new DiffusionCommitRef())
      ->setCommitterName($parts[0])
      ->setCommitterEmail($parts[1])
      ->setAuthorName($parts[2])
      ->setAuthorEmail($parts[3])
      ->setHashes($hashes)
      ->setMessage($parts[5]);
  }

  private function loadMercurialCommitRef() {
    $repository = $this->getRepository();

    list($stdout) = $repository->execxLocalCommand(
      'log --template %s --rev %s',
      '{author}\\n{desc}',
      hgsprintf('%s', $this->identifier));

    list($author, $message) = explode("\n", $stdout, 2);

    $author = phutil_utf8ize($author);
    $message = phutil_utf8ize($message);

    list($author_name, $author_email) = $this->splitUserIdentifier($author);

    $hashes = array(
      id(new DiffusionCommitHash())
        ->setHashType(ArcanistDifferentialRevisionHash::HASH_MERCURIAL_COMMIT)
        ->setHashValue($this->identifier),
    );

    return id(new DiffusionCommitRef())
      ->setAuthorName($author_name)
      ->setAuthorEmail($author_email)
      ->setMessage($message)
      ->setHashes($hashes);
  }

  private function loadSubversionCommitRef() {
    $repository = $this->getRepository();

    list($xml) = $repository->execxRemoteCommand(
      'log --xml --limit 1 %s',
      $repository->getSubversionPathURI(null, $this->identifier));

    // Subversion may send us back commit messages which won't parse because
    // they have non UTF-8 garbage in them. Slam them into valid UTF-8.
    $xml = phutil_utf8ize($xml);
    $log = new SimpleXMLElement($xml);
    $entry = $log->logentry[0];

    $author = (string)$entry->author;
    $message = (string)$entry->msg;

    list($author_name, $author_email) = $this->splitUserIdentifier($author);

    // No hashes in Subversion.
    $hashes = array();

    return id(new DiffusionCommitRef())
      ->setAuthorName($author_name)
      ->setAuthorEmail($author_email)
      ->setMessage($message)
      ->setHashes($hashes);
  }

  private function splitUserIdentifier($user) {
    $email = new PhutilEmailAddress($user);

    if ($email->getDisplayName() || $email->getDomainName()) {
      $user_name = $email->getDisplayName();
      $user_email = $email->getAddress();
    } else {
      $user_name = $email->getAddress();
      $user_email = null;
    }

    return array($user_name, $user_email);
  }

}
