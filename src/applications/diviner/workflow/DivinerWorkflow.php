<?php

abstract class DivinerWorkflow extends PhutilArgumentWorkflow {

  private $config;
  private $bookConfigPath;

  public function getBookConfigPath() {
    return $this->bookConfigPath;
  }

  public function isExecutable() {
    return true;
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

    // If the book specifies a "root", resolve it; otherwise, use the directory
    // the book configuration file lives in.
    $full_path = dirname(Filesystem::resolvePath($book_path));
    if (empty($book['root'])) {
      $book['root'] = '.';
    }
    $book['root'] = Filesystem::resolvePath($book['root'], $full_path);

    // Make sure we have a valid book name.
    if (!isset($book['name'])) {
      throw new PhutilArgumentUsageException(
        "Book configuration '{$book_path}' is missing required ".
        "property 'name'.");
    }

    if (!preg_match('/^[a-z][a-z-]*$/', $book['name'])) {
      $name = $book['name'];
      throw new PhutilArgumentUsageException(
        "Book configuration '{$book_path}' has name '{$name}', but book names ".
        "must include only lowercase letters and hyphens.");
    }

    $this->bookConfigPath = $book_path;
    $this->config = $book;
  }

}
