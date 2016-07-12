<?php

final class NuanceGitHubRepositorySourceDefinition
  extends NuanceSourceDefinition {

  public function getName() {
    return pht('GitHub Repository');
  }

  public function getSourceDescription() {
    return pht('Import issues and pull requests from a GitHub repository.');
  }

  public function getSourceTypeConstant() {
    return 'github.repository';
  }

  public function hasImportCursors() {
    return true;
  }

  protected function newImportCursors() {
    return array(
      id(new NuanceGitHubRepositoryImportCursor())
        ->setCursorKey('events.repository'),
      id(new NuanceGitHubIssuesImportCursor())
        ->setCursorKey('events.issues'),
    );
  }

}
