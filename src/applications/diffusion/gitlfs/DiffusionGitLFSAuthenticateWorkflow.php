<?php

final class DiffusionGitLFSAuthenticateWorkflow
  extends DiffusionGitSSHWorkflow {

  protected function didConstruct() {
    $this->setName('git-lfs-authenticate');
    $this->setArguments(
      array(
        array(
          'name' => 'argv',
          'wildcard' => true,
        ),
      ));
  }

  protected function identifyRepository() {
    return $this->loadRepositoryWithPath($this->getLFSPathArgument());
  }

  private function getLFSPathArgument() {
    return $this->getLFSArgument(0);
  }

  private function getLFSOperationArgument() {
    return $this->getLFSArgument(1);
  }

  private function getLFSArgument($position) {
    $args = $this->getArgs();
    $argv = $args->getArg('argv');

    if (!isset($argv[$position])) {
      throw new Exception(
        pht(
          'Expected `git-lfs-authenticate <path> <operation>`, but received '.
          'too few arguments.'));
    }

    return $argv[$position];
  }

  protected function executeRepositoryOperations() {
    $operation = $this->getLFSOperationArgument();

    // NOTE: We aren't checking write access here, even for "upload". The
    // HTTP endpoint should be able to do that for us.

    switch ($operation) {
      case 'upload':
      case 'download':
        break;
      default:
        throw new Exception(
          pht(
            'Git LFS operation "%s" is not supported by this server.',
            $operation));
    }

    $repository = $this->getRepository();

    if (!$repository->isGit()) {
      throw new Exception(
        pht(
          'Repository "%s" is not a Git repository. Git LFS is only '.
          'supported for Git repositories.',
          $repository->getDisplayName()));
    }

    if (!$repository->canUseGitLFS()) {
      throw new Exception(
        pht('Git LFS is not enabled for this repository.'));
    }

    // NOTE: This is usually the same as the default URI (which does not
    // need to be specified in the response), but the protocol or domain may
    // differ in some situations.

    $lfs_uri = $repository->getGitLFSURI('info/lfs');

    // Generate a temporary token to allow the user to acces LFS over HTTP.
    // This works even if normal HTTP repository operations are not available
    // on this host, and does not require the user to have a VCS password.

    $user = $this->getUser();

    $authorization = DiffusionGitLFSTemporaryTokenType::newHTTPAuthorization(
      $repository,
      $user,
      $operation);

    $headers = array(
      'authorization' => $authorization,
    );

    $result = array(
      'header' => $headers,
      'href' => $lfs_uri,
    );
    $result = phutil_json_encode($result);

    $this->writeIO($result);
    $this->waitForGitClient();

    return 0;
  }

}
