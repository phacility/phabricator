<?php

final class PhabricatorRepositorySchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorRepository());

    $this->buildRawSchema(
      id(new PhabricatorRepository())->getApplicationName(),
      PhabricatorRepository::TABLE_BADCOMMIT,
      array(
        'fullCommitName' => 'text64',
        'description' => 'text',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('fullCommitName'),
          'unique' => true,
        ),
      ));

    $this->buildRawSchema(
      id(new PhabricatorRepository())->getApplicationName(),
      PhabricatorRepository::TABLE_COVERAGE,
      array(
        'id' => 'auto',
        'branchID' => 'id',
        'commitID' => 'id',
        'pathID' => 'id',
        'coverage' => 'bytes',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('id'),
          'unique' => true,
        ),
        'key_path' => array(
          'columns' => array('branchID', 'pathID', 'commitID'),
          'unique' => true,
        ),
      ));

    $this->buildRawSchema(
      id(new PhabricatorRepository())->getApplicationName(),
      PhabricatorRepository::TABLE_FILESYSTEM,
      array(
        'repositoryID' => 'id',
        'parentID' => 'id',
        'svnCommit' => 'uint32',
        'pathID' => 'id',
        'existed' => 'bool',
        'fileType' => 'uint32',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('repositoryID', 'parentID', 'pathID', 'svnCommit'),
          'unique' => true,
        ),
        'repositoryID' => array(
          'columns' => array('repositoryID', 'svnCommit'),
        ),
      ));

    $this->buildRawSchema(
      id(new PhabricatorRepository())->getApplicationName(),
      PhabricatorRepository::TABLE_LINTMESSAGE,
      array(
        'id' => 'auto',
        'branchID' => 'id',
        'path' => 'text',
        'line' => 'uint32',
        'authorPHID' => 'phid?',
        'code' => 'text32',
        'severity' => 'text16',
        'name' => 'text255',
        'description' => 'text',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('id'),
          'unique' => true,
        ),
        'branchID' => array(
          'columns' => array('branchID', 'path(64)'),
        ),
        'branchID_2' => array(
          'columns' => array('branchID', 'code', 'path(64)'),
        ),
        'key_author' => array(
          'columns' => array('authorPHID'),
        ),
      ));

    $this->buildRawSchema(
      id(new PhabricatorRepository())->getApplicationName(),
      PhabricatorRepository::TABLE_PARENTS,
      array(
        'id' => 'auto',
        'childCommitID' => 'id',
        'parentCommitID' => 'id',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('id'),
          'unique' => true,
        ),
        'key_child' => array(
          'columns' => array('childCommitID', 'parentCommitID'),
          'unique' => true,
        ),
        'key_parent' => array(
          'columns' => array('parentCommitID'),
        ),
      ));

    $this->buildRawSchema(
      id(new PhabricatorRepository())->getApplicationName(),
      PhabricatorRepository::TABLE_PATH,
      array(
        'id' => 'auto',
        'path' => 'text',
        'pathHash' => 'bytes32',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('id'),
          'unique' => true,
        ),
        'pathHash' => array(
          'columns' => array('pathHash'),
          'unique' => true,
        ),
      ));

    $this->buildRawSchema(
      id(new PhabricatorRepository())->getApplicationName(),
      PhabricatorRepository::TABLE_PATHCHANGE,
      array(
        'repositoryID' => 'id',
        'pathID' => 'id',
        'commitID' => 'id',
        'targetPathID' => 'id?',
        'targetCommitID' => 'id?',
        'changeType' => 'uint32',
        'fileType' => 'uint32',
        'isDirect' => 'bool',
        'commitSequence' => 'uint32',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('commitID', 'pathID'),
          'unique' => true,
        ),
        'repositoryID' => array(
          'columns' => array('repositoryID', 'pathID', 'commitSequence'),
        ),
      ));

    $this->buildRawSchema(
      id(new PhabricatorRepository())->getApplicationName(),
      PhabricatorRepository::TABLE_SUMMARY,
      array(
        'repositoryID' => 'id',
        'size' => 'uint32',
        'lastCommitID' => 'id',
        'epoch' => 'epoch?',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('repositoryID'),
          'unique' => true,
        ),
        'key_epoch' => array(
          'columns' => array('epoch'),
        ),
      ));

  }

}
