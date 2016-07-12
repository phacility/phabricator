<?php

abstract class PhabricatorWorkingCopyTestCase extends PhabricatorTestCase {

  private $dirs = array();

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  protected function buildBareRepository($callsign) {
    $existing_repository = id(new PhabricatorRepositoryQuery())
      ->withCallsigns(array($callsign))
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->executeOne();
    if ($existing_repository) {
      $existing_repository->delete();
    }

    $data_dir = dirname(__FILE__).'/data/';

    $types = array(
      'svn'   => PhabricatorRepositoryType::REPOSITORY_TYPE_SVN,
      'hg'    => PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL,
      'git'   => PhabricatorRepositoryType::REPOSITORY_TYPE_GIT,
    );

    $hits = array();
    foreach ($types as $type => $const) {
      $path = $data_dir.$callsign.'.'.$type.'.tgz';
      if (Filesystem::pathExists($path)) {
        $hits[$const] = $path;
      }
    }

    if (!$hits) {
      throw new Exception(
        pht(
          "No test data for callsign '%s'. Expected an archive ".
          "like '%s' in '%s'.",
          $callsign,
          "{$callsign}.git.tgz",
          $data_dir));
    }

    if (count($hits) > 1) {
      throw new Exception(
        pht(
          "Expected exactly one archive matching callsign '%s', ".
          "found too many: %s",
          $callsign,
          implode(', ', $hits)));
    }

    $path = head($hits);
    $vcs_type = head_key($hits);

    $dir = PhutilDirectoryFixture::newFromArchive($path);
    $local = new TempFile('.ignore');

    $user = $this->generateNewTestUser();
    $repo = PhabricatorRepository::initializeNewRepository($user)
      ->setCallsign($callsign)
      ->setName(pht('Test Repo "%s"', $callsign))
      ->setVersionControlSystem($vcs_type)
      ->setLocalPath(dirname($local).'/'.$callsign)
      ->setDetail('remote-uri', 'file://'.$dir->getPath().'/');

    $this->didConstructRepository($repo);

    $repo->save();

    // Keep the disk resources around until we exit.
    $this->dirs[] = $dir;
    $this->dirs[] = $local;

    return $repo;
  }

  protected function didConstructRepository(PhabricatorRepository $repository) {
    return;
  }

  protected function buildPulledRepository($callsign) {
    $repository = $this->buildBareRepository($callsign);

    id(new PhabricatorRepositoryPullEngine())
      ->setRepository($repository)
      ->pullRepository();

    return $repository;
  }

  protected function buildDiscoveredRepository($callsign) {
    $repository = $this->buildPulledRepository($callsign);

    id(new PhabricatorRepositoryDiscoveryEngine())
      ->setRepository($repository)
      ->discoverCommits();

    return $repository;
  }


}
