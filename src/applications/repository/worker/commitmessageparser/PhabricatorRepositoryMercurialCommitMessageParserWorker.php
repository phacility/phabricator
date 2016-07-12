<?php

final class PhabricatorRepositoryMercurialCommitMessageParserWorker
  extends PhabricatorRepositoryCommitMessageParserWorker {

  protected function getFollowupTaskClass() {
    return 'PhabricatorRepositoryMercurialCommitChangeParserWorker';
  }

}
