<?php

final class DiffusionLowLevelMercurialCommitQuery
  extends DiffusionLowLevelQuery {

  private $identifier;

  public function withIdentifier($identifier) {
    $this->identifier = $identifier;
    return $this;
  }

  protected function executeQuery() {
    $repository = $this->getRepository();

    list($stdout) = $repository->execxLocalCommand(
      'log --template %s --rev %s',
      '{author}\\n{desc}',
      hgsprintf('%s', $this->identifier));

    list($author, $message) = explode("\n", $stdout, 2);

    $author = phutil_utf8ize($author);
    $message = phutil_utf8ize($message);

    $email = new PhutilEmailAddress($author);
    if ($email->getDisplayName() || $email->getDomainName()) {
      $author_name = $email->getDisplayName();
      $author_email = $email->getAddress();
    } else {
      $author_name = $email->getAddress();
      $author_email = null;
    }

    return id(new DiffusionCommitRef())
      ->setAuthorName($author_name)
      ->setAuthorEmail($author_email)
      ->setMessage($message);
  }

}
