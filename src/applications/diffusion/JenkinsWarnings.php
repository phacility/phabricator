<?php

final class JenkinsWarnings {

  private $apiRequest;

  public function __construct($job_name, $build_number, $api_method) {
    $this->apiRequest = JenkinsAPIRequest::create()
      ->addJob($job_name)
      ->addBuild($build_number)
      ->setSuffix($api_method);
  }

  public function get(array $file_filter) {
    $raw_warnings = $this->apiRequest
      ->setParams(array('tree' => 'warnings[*]'))
      ->query()
      ->warnings;

    return $this->filter($this->groupByFile($raw_warnings), $file_filter);
  }

  private function groupByFile(array $raw_warnings) {
    $grouped_warnings = array();

    foreach ($raw_warnings as $raw_warning) {
      $file_name = preg_replace(
        '#.*/jobs/[^\/]*/workspace/(.*)$#',
        '$1',
        $raw_warning->fileName);

      if (!idx($grouped_warnings, $file_name)) {
        $grouped_warnings[$file_name] = array();
      }

      // Decode due https://github.com/squizlabs/PHP_CodeSniffer/issues/315
      $grouped_warnings[$file_name][] = array(
        'line' => $raw_warning->primaryLineNumber,
        'message' => htmlspecialchars_decode($raw_warning->message, ENT_QUOTES),
        'priority' => $raw_warning->priority,
      );
    }

    return $grouped_warnings;
  }

  private function filter(array $warnings, array $allowed_files) {
    $filtered_warnings = array();
    $allowed_files = array_flip($allowed_files);

    foreach ($warnings as $file => $file_warnings) {
      if (!idx($allowed_files, $file)) {
        continue;
      }

      $filtered_warnings[$file] = $file_warnings;
    }

    return $filtered_warnings;
  }

}
