<?php

final class DiffusionGitLFSTemporaryTokenType
  extends PhabricatorAuthTemporaryTokenType {

  const TOKENTYPE = 'diffusion.git.lfs';
  const HTTP_USERNAME = '@git-lfs';

  public function getTokenTypeDisplayName() {
    return pht('Git Large File Storage');
  }

  public function getTokenReadableTypeName(
    PhabricatorAuthTemporaryToken $token) {
    return pht('Git LFS Token');
  }

}
