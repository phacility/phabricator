<?php

final class PhabricatorRepositoryURIIndex
  extends PhabricatorRepositoryDAO {

  protected $repositoryPHID;
  protected $repositoryURI;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'repositoryURI' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_repository' => array(
          'columns' => array('repositoryPHID'),
        ),
        'key_uri' => array(
          'columns' => array('repositoryURI(128)'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function updateRepositoryURIs(
    $repository_phid,
    array $uris) {

    $table = new self();
    $conn_w = $table->establishConnection('w');

    $sql = array();
    foreach ($uris as $key => $uri) {
      if (!strlen($uri)) {
        unset($uris[$key]);
        continue;
      }

      $sql[] = qsprintf(
        $conn_w,
        '(%s, %s)',
        $repository_phid,
        $uri);
    }

    $table->openTransaction();

      queryfx(
        $conn_w,
        'DELETE FROM %R WHERE repositoryPHID = %s',
        $table,
        $repository_phid);

      if ($sql) {
        queryfx(
          $conn_w,
          'INSERT INTO %R (repositoryPHID, repositoryURI) VALUES %LQ',
          $table,
          $sql);
      }

    $table->saveTransaction();

  }

}
