<?php

final class PhabricatorRepositoryGitCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  protected function getFollowupTaskClass() {
    return 'PhabricatorRepositoryGitCommitChangeParserWorker';
  }

}
