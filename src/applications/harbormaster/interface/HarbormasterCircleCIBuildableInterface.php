<?php

/**
 * Support for CircleCI.
 */
interface HarbormasterCircleCIBuildableInterface {

  public function getCircleCIGitHubRepositoryURI();
  public function getCircleCIBuildIdentifierType();
  public function getCircleCIBuildIdentifier();

}
