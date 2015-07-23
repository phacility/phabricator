<?php

/**
 * Normalize repository URIs. For example, these URIs are generally equivalent
 * and all point at the same repository:
 *
 *   ssh://user@host/repo
 *   ssh://user@host/repo/
 *   ssh://user@host:22/repo
 *   user@host:/repo
 *   ssh://user@host/repo.git
 *
 * This class can be used to normalize URIs like this, in order to detect
 * alternate spellings of the same repository URI. In particular, the
 * @{method:getNormalizedPath} method will return:
 *
 *   repo
 *
 * ...for all of these URIs. Generally, usage looks like this:
 *
 *   $norm_a = new PhabricatorRepositoryURINormalizer($type, $uri_a);
 *   $norm_b = new PhabricatorRepositoryURINormalizer($type, $uri_b);
 *
 *   if ($norm_a->getNormalizedPath() == $norm_b->getNormalizedPath()) {
 *     // URIs appear to point at the same repository.
 *   } else {
 *     // URIs are very unlikely to be the same repository.
 *   }
 *
 * Because a repository can be hosted at arbitrarly many arbitrary URIs, there
 * is no way to completely prevent false negatives by only examining URIs
 * (that is, repositories with totally different URIs could really be the same).
 * However, normalization is relatively agressive and false negatives should
 * be rare: if normalization says two URIs are different repositories, they
 * probably are.
 *
 * @task normal Normalizing URIs
 */
final class PhabricatorRepositoryURINormalizer extends Phobject {

  const TYPE_GIT = 'git';
  const TYPE_SVN = 'svn';
  const TYPE_MERCURIAL = 'hg';

  private $type;
  private $uri;

  public function __construct($type, $uri) {
    switch ($type) {
      case self::TYPE_GIT:
      case self::TYPE_SVN:
      case self::TYPE_MERCURIAL:
        break;
      default:
        throw new Exception(pht('Unknown URI type "%s"!', $type));
    }

    $this->type = $type;
    $this->uri = $uri;
  }


/* -(  Normalizing URIs  )--------------------------------------------------- */


  /**
   * @task normal
   */
  public function getPath() {
    switch ($this->type) {
      case self::TYPE_GIT:
        $uri = new PhutilURI($this->uri);
        if ($uri->getProtocol()) {
          return $uri->getPath();
        }

        $uri = new PhutilGitURI($this->uri);
        if ($uri->getDomain()) {
          return $uri->getPath();
        }

        return $this->uri;
      case self::TYPE_SVN:
      case self::TYPE_MERCURIAL:
        $uri = new PhutilURI($this->uri);
        if ($uri->getProtocol()) {
          return $uri->getPath();
        }

        return $this->uri;
    }
  }


  /**
   * @task normal
   */
  public function getNormalizedPath() {
    $path = $this->getPath();
    $path = trim($path, '/');

    switch ($this->type) {
      case self::TYPE_GIT:
        $path = preg_replace('/\.git$/', '', $path);
        break;
      case self::TYPE_SVN:
      case self::TYPE_MERCURIAL:
        break;
    }

    // If this is a Phabricator URI, strip it down to the callsign. We mutably
    // allow you to clone repositories as "/diffusion/X/anything.git", for
    // example.

    $matches = null;
    if (preg_match('@^(diffusion/[A-Z]+)@', $path, $matches)) {
      $path = $matches[1];
    }

    return $path;
  }

}
