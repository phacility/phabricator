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

    // See T5028. The "%B" (raw body) mode is not present in very old versions
    // of Git. Use "%s" and "%b" ("subject" and "wrapped body") as an
    // approximation.

    $git_binary = PhutilBinaryAnalyzer::getForBinary('git');
    $git_version = $git_binary->getBinaryVersion();
    if (version_compare($git_version, '1.7.2', '>=')) {
      $body_format = '%B';
      $split_body = false;
    } else {
      $body_format = '%s%x00%b';
      $split_body = true;
    }

    $argv = array();

    $argv[] = '-n';
    $argv[] = '1';

    $argv[] = '--encoding=UTF-8';

    $argv[] = sprintf(
      '--format=%s',
      implode(
        '%x00',
        array(
          '%e',
          '%cn',
          '%ce',
          '%an',
          '%ae',
          '%T',
          '%at',
          $body_format,

          // The "git log" output includes a trailing newline. We want to
          // faithfully capture only the exact text of the commit message,
          // so include an explicit terminator: this makes sure the exact
          // body text is surrounded by "\0" characters.
          '~',
        )));

    // Even though we pass --encoding here, git doesn't always succeed, so
    // we try a little harder, since git *does* tell us what the actual encoding
    // is correctly (unless it doesn't; encoding is sometimes empty).
    list($info) = $repository->execxLocalCommand(
      'log -n 1 %Ls %s --',
      $argv,
      gitsprintf('%s', $this->identifier));

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

    $author_epoch = (int)$parts[5];
    if (!$author_epoch) {
      $author_epoch = null;
    }

    if ($split_body) {
      // Here, the body is: "subject", "\0", "wrapped body". Stitch the
      // pieces back together by putting a newline between them if both
      // parts are nonempty.

      $head = $parts[6];
      $tail = $parts[7];

      if (strlen($head) && strlen($tail)) {
        $body = $head."\n\n".$tail;
      } else if (strlen($head)) {
        $body = $head;
      } else if (strlen($tail)) {
        $body = $tail;
      } else {
        $body = '';
      }
    } else {
      // Here, the body is the raw unwrapped body.
      $body = $parts[6];
    }

    return id(new DiffusionCommitRef())
      ->setCommitterName($parts[0])
      ->setCommitterEmail($parts[1])
      ->setAuthorName($parts[2])
      ->setAuthorEmail($parts[3])
      ->setHashes($hashes)
      ->setAuthorEpoch($author_epoch)
      ->setMessage($body);
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
