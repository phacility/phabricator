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

  protected function readBookConfiguration(PhutilArgumentParser $args) {
    $book_path = $args->getArg('book');
    if ($book_path === null) {
      throw new PhutilArgumentUsageException(
        "Specify a Diviner book configuration file with --book.");
    }

    $book_data = Filesystem::readFile($book_path);
    $book = json_decode($book_data, true);
    if (!is_array($book)) {
      throw new PhutilArgumentUsageException(
        "Book configuration '{$book_path}' is not in JSON format.");
    }

    PhutilTypeSpec::checkMap(
      $book,
      array(
        'name' => 'string',
        'title' => 'optional string',
        'short' => 'optional string',
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

    if (!preg_match('/^[a-z][a-z-]*$/', $book['name'])) {
      $name = $book['name'];
      throw new PhutilArgumentUsageException(
        "Book configuration '{$book_path}' has name '{$name}', but book names ".
        "must include only lowercase letters and hyphens.");
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
