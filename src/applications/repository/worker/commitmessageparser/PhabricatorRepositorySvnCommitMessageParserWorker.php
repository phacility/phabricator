<?php

final class PhabricatorRepositorySvnCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  protected function getFollowupTaskClass() {
    return 'PhabricatorRepositorySvnCommitChangeParserWorker';
  }

}
