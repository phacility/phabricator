<?php

abstract class DivinerWorkflow extends PhabricatorManagementWorkflow {

  private $config;
  private $bookConfigPath;

  public function getBookConfigPath() {
    return $this->bookConfigPath;
  }

  protected function getConfig($key, $default = null) {
    return idx($this->config, $key, $default);
  }

  protected function getAllConfig() {
    return $this->config;
  }

  protected function readBookConfiguration($book_path) {
    if ($book_path === null) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a Diviner book configuration file with %s.',
          '--book'));
    }

    $book_data = Filesystem::readFile($book_path);
    $book = phutil_json_decode($book_data);

    PhutilTypeSpec::checkMap(
      $book,
      array(
        'name' => 'string',
        'title' => 'optional string',
        'short' => 'optional string',
        'preface' => 'optional string',
        'root' => 'optional string',
        'uri.source' => 'optional string',
        'rules' => 'optional map<regex, string>',
        'exclude' => 'optional regex|list<regex>',
        'groups' => 'optional map<string, map<string, wild>>',
      ));

    // If the book specifies a "root", resolve it; otherwise, use the directory
    // the book configuration file lives in.
    $full_path = dirname(Filesystem::resolvePath($book_path));
    if (empty($book['root'])) {
      $book['root'] = '.';
    }
    $book['root'] = Filesystem::resolvePath($book['root'], $full_path);

    if (!preg_match('/^[a-z][a-z-]*\z/', $book['name'])) {
      $name = $book['name'];
      throw new PhutilArgumentUsageException(
        pht(
          "Book configuration '%s' has name '%s', but book names must ".
          "include only lowercase letters and hyphens.",
          $book_path,
          $name));
    }

    foreach (idx($book, 'groups', array()) as $group) {
      PhutilTypeSpec::checkmap(
        $group,
        array(
          'name' => 'string',
          'include' => 'optional regex|list<regex>',
        ));
    }

    $this->bookConfigPath = $book_path;
    $this->config = $book;
  }

}
