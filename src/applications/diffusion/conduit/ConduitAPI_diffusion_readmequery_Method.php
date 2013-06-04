<?php

/**
 * @group conduit
 */
final class ConduitAPI_diffusion_readmequery_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function getMethodDescription() {
    return
      'Retrieve any "readme" that can be found for a set of paths in '.
      'repository.';
  }

  public function defineReturnType() {
    return 'string';
  }

  protected function defineCustomParamTypes() {
    return array(
      'paths' => 'required array <string>',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $path_dicts = $request->getValue('paths', array());
    $paths = array();
    foreach ($path_dicts as $dict) {
      $paths[] = DiffusionRepositoryPath::newFromDictionary($dict);
    }

    $readme = '';
    foreach ($paths as $result_path) {
      $file_type = $result_path->getFileType();
      if (($file_type != ArcanistDiffChangeType::FILE_NORMAL) &&
          ($file_type != ArcanistDiffChangeType::FILE_TEXT)) {
        // Skip directories, etc.
        continue;
      }

      $path = $result_path->getPath();

      if (preg_match('/^readme(|\.txt|\.remarkup|\.rainbow|\.md)$/i', $path)) {
        $readme = $result_path;
        break;
      }
    }

    if (!$readme) {
      return '';
    }

    $readme_request = DiffusionRequest::newFromDictionary(
      array(
        'user' => $request->getUser(),
        'repository' => $drequest->getRepository(),
        'commit' => $drequest->getStableCommitName(),
        'path' => $readme->getFullPath(),
      ));

    $file_content = DiffusionFileContent::newFromConduit(
      DiffusionQuery::callConduitWithDiffusionRequest(
        $request->getUser(),
        $readme_request,
        'diffusion.filecontentquery',
        array(
          'commit' => $drequest->getStableCommitName(),
          'path' => $readme->getFullPath(),
          'needsBlame' => false,
        )));
    $readme_content = $file_content->getCorpus();

    if (preg_match('/\\.txt$/', $readme->getPath())) {
      $readme_content = phutil_escape_html_newlines($readme_content);

      $class = null;
    } else if (preg_match('/\\.rainbow$/', $readme->getPath())) {
      $highlighter = new PhutilRainbowSyntaxHighlighter();
      $readme_content = $highlighter
        ->getHighlightFuture($readme_content)
        ->resolve();
      $readme_content = phutil_escape_html_newlines($readme_content);

      require_celerity_resource('syntax-highlighting-css');
      $class = 'remarkup-code';
    } else {
      // Markup extensionless files as remarkup so we get links and such.
      $engine = PhabricatorMarkupEngine::newDiffusionMarkupEngine();
      $engine->setConfig('viewer', $request->getUser());
      $readme_content = $engine->markupText($readme_content);

      $class = 'phabricator-remarkup';
    }

    $readme_content = phutil_tag(
      'div',
      array(
        'class' => $class,
      ),
      $readme_content);

    return $readme_content;
  }

}
