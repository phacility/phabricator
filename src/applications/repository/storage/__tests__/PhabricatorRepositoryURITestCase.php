<?php

final class PhabricatorRepositoryURITestCase
  extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testRepositoryURICanonicalization() {
    $repo = id(new PhabricatorRepository())
      ->makeEphemeral()
      ->setID(123);

    $tests = array(
      '/diffusion/123' => '/diffusion/123/',
      '/diffusion/123/' => '/diffusion/123/',
      '/diffusion/123/browse/master/' => '/diffusion/123/browse/master/',
      '/kangaroo/' => null,
    );

    foreach ($tests as $input => $expect) {
      $this->assertEqual(
        $expect,
        $repo->getCanonicalPath($input),
        pht('Canonical Path (ID, No Callsign): %s', $input));
    }

    $repo->setCallsign('XYZ');

    $tests = array(
      '/diffusion/123' => '/diffusion/XYZ/',
      '/diffusion/123/' => '/diffusion/XYZ/',
      '/diffusion/123/browse/master/' => '/diffusion/XYZ/browse/master/',
      '/diffusion/XYZ' => '/diffusion/XYZ/',
      '/diffusion/XYZ/' => '/diffusion/XYZ/',
      '/diffusion/XYZ/browse/master/' => '/diffusion/XYZ/browse/master/',
      '/diffusion/ABC/' => '/diffusion/XYZ/',
      '/kangaroo/' => null,
      '/R1:abcdef' => '/rXYZabcdef',
    );

    foreach ($tests as $input => $expect) {
      $this->assertEqual(
        $expect,
        $repo->getCanonicalPath($input),
        pht('Canonical Path (ID, Callsign): %s', $input));
    }
  }

  public function testURIGeneration() {
    $svn = PhabricatorRepositoryType::REPOSITORY_TYPE_SVN;
    $git = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
    $hg = PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL;

    $user = $this->generateNewTestUser();

    $http_secret = id(new PassphraseSecret())->setSecretData('quack')->save();

    $http_credential = PassphraseCredential::initializeNewCredential($user)
      ->setCredentialType(PassphrasePasswordCredentialType::CREDENTIAL_TYPE)
      ->setProvidesType(PassphrasePasswordCredentialType::PROVIDES_TYPE)
      ->setUsername('duck')
      ->setSecretID($http_secret->getID())
      ->save();

    $repo = PhabricatorRepository::initializeNewRepository($user)
      ->setVersionControlSystem($svn)
      ->setName(pht('Test Repo'))
      ->setCallsign('TESTREPO')
      ->setCredentialPHID($http_credential->getPHID())
      ->save();

    // Test HTTP URIs.

    $repo->setDetail('remote-uri', 'http://example.com/');
    $repo->setVersionControlSystem($svn);

    $this->assertEqual('http://example.com/', $repo->getRemoteURI());
    $this->assertEqual('http://example.com/', $repo->getPublicCloneURI());
    $this->assertEqual('http://example.com/',
      $repo->getRemoteURIEnvelope()->openEnvelope());

    $repo->setVersionControlSystem($git);

    $this->assertEqual('http://example.com/', $repo->getRemoteURI());
    $this->assertEqual('http://example.com/', $repo->getPublicCloneURI());
    $this->assertEqual('http://duck:quack@example.com/',
      $repo->getRemoteURIEnvelope()->openEnvelope());

    $repo->setVersionControlSystem($hg);

    $this->assertEqual('http://example.com/', $repo->getRemoteURI());
    $this->assertEqual('http://example.com/', $repo->getPublicCloneURI());
    $this->assertEqual('http://duck:quack@example.com/',
      $repo->getRemoteURIEnvelope()->openEnvelope());

    // Test SSH URIs.

    $repo->setDetail('remote-uri', 'ssh://example.com/');
    $repo->setVersionControlSystem($svn);

    $this->assertEqual('ssh://example.com/', $repo->getRemoteURI());
    $this->assertEqual('ssh://example.com/', $repo->getPublicCloneURI());
    $this->assertEqual('ssh://example.com/',
      $repo->getRemoteURIEnvelope()->openEnvelope());

    $repo->setVersionControlSystem($git);

    $this->assertEqual('ssh://example.com/', $repo->getRemoteURI());
    $this->assertEqual('ssh://example.com/', $repo->getPublicCloneURI());
    $this->assertEqual('ssh://example.com/',
      $repo->getRemoteURIEnvelope()->openEnvelope());

    $repo->setVersionControlSystem($hg);

    $this->assertEqual('ssh://example.com/', $repo->getRemoteURI());
    $this->assertEqual('ssh://example.com/', $repo->getPublicCloneURI());
    $this->assertEqual('ssh://example.com/',
      $repo->getRemoteURIEnvelope()->openEnvelope());

    // Test Git URIs.

    $repo->setDetail('remote-uri', 'git@example.com:path.git');
    $repo->setVersionControlSystem($git);

    $this->assertEqual('git@example.com:path.git', $repo->getRemoteURI());
    $this->assertEqual('git@example.com:path.git', $repo->getPublicCloneURI());
    $this->assertEqual('git@example.com:path.git',
      $repo->getRemoteURIEnvelope()->openEnvelope());

    // Test SVN "Import Only" paths.

    $repo->setDetail('remote-uri', 'http://example.com/');
    $repo->setVersionControlSystem($svn);
    $repo->setDetail('svn-subpath', 'projects/example/');

    $this->assertEqual('http://example.com/', $repo->getRemoteURI());
    $this->assertEqual(
      'http://example.com/projects/example/',
      $repo->getPublicCloneURI());
    $this->assertEqual('http://example.com/',
      $repo->getRemoteURIEnvelope()->openEnvelope());

  }

}
