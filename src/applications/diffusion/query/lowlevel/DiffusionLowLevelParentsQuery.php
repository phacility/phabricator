<?php

final class DiffusionLowLevelParentsQuery
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
        $result = $this->loadGitParents();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $result = $this->loadMercurialParents();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $result = $this->loadSubversionParents();
        break;
      default:
        throw new Exception(pht('Unsupported repository type "%s"!', $type));
    }

    return $result;
  }

  private function loadGitParents() {
    $repository = $this->getRepository();

    list($stdout) = $repository->execxLocalCommand(
      'log -n 1 %s %s --',
      '--format=%P',
      gitsprintf('%s', $this->identifier));

    return preg_split('/\s+/', trim($stdout));
  }

  private function loadMercurialParents() {
    $repository = $this->getRepository();

    $hg_analyzer = PhutilBinaryAnalyzer::getForBinary('hg');
    if ($hg_analyzer->isMercurialTemplatePnodeAvailable()) {
      $hg_log_template = '{p1.node} {p2.node}';
    } else {
      $hg_log_template = '{p1node} {p2node}';
    }

    list($stdout) = $repository->execxLocalCommand(
      'log --limit 1 --template %s --rev %s',
      $hg_log_template,
      $this->identifier);

    $hashes = preg_split('/\s+/', trim($stdout));
    foreach ($hashes as $key => $value) {
      // We get 40-character hashes but also get the "000000..." hash for
      // missing parents; ignore it.
      if (preg_match('/^0+\z/', $value)) {
        unset($hashes[$key]);
      }
    }

    return $hashes;
  }

  private function loadSubversionParents() {
    $repository = $this->getRepository();
    $identifier = $this->identifier;

    $refs = id(new DiffusionCachedResolveRefsQuery())
      ->setRepository($repository)
      ->withRefs(array($identifier))
      ->execute();
    if (!$refs) {
      throw new Exception(
        pht(
          'No commit "%s" in this repository.',
          $identifier));
    }

    $n = (int)$identifier;
    if ($n > 1) {
      $ids = array($n - 1);
    } else {
      $ids = array();
    }

    return $ids;
  }

}
