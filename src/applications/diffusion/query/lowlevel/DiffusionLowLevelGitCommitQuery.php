<?php

final class DiffusionLowLevelGitCommitQuery extends DiffusionLowLevelQuery {

  private $identifier;

  public function withIdentifier($identifier) {
    $this->identifier = $identifier;
    return $this;
  }

  protected function executeQuery() {
    $repository = $this->getRepository();

    // NOTE: %B was introduced somewhat recently in git's history, so pull
    // commit message information with %s and %b instead.

    // Even though we pass --encoding here, git doesn't always succeed, so
    // we try a little harder, since git *does* tell us what the actual encoding
    // is correctly (unless it doesn't; encoding is sometimes empty).
    list($info) = $repository->execxLocalCommand(
      'log -n 1 --encoding=%s --format=%s %s --',
      'UTF-8',
      implode('%x00', array('%e', '%cn', '%ce', '%an', '%ae', '%s%n%n%b')),
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

    return id(new DiffusionCommitRef())
      ->setCommitterName($parts[0])
      ->setCommitterEmail($parts[1])
      ->setAuthorName($parts[2])
      ->setAuthorEmail($parts[3])
      ->setMessage($parts[4]);
  }

}
