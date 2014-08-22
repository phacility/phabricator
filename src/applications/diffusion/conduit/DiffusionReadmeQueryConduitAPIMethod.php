<?php

final class DiffusionReadmeQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.readmequery';
  }

  public function getMethodDescription() {
    return
      pht('Retrieve any "readme" that can be found for a set of paths in '.
          'repository.');
  }

  public function defineReturnType() {
    return 'string';
  }

  protected function defineCustomParamTypes() {
    return array(
      'paths' => 'required array <string>',
      'commit' => 'optional string',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $path_dicts = $request->getValue('paths', array());
    $paths = array();
    foreach ($path_dicts as $dict) {
      $paths[] = DiffusionRepositoryPath::newFromDictionary($dict);
    }

    $best = -1;
    $readme = '';
    $best_render_type = 'plain';
    foreach ($paths as $result_path) {
      $file_type = $result_path->getFileType();
      if (($file_type != ArcanistDiffChangeType::FILE_NORMAL) &&
          ($file_type != ArcanistDiffChangeType::FILE_TEXT)) {
        // Skip directories, etc.
        continue;
      }

      $path = strtolower($result_path->getPath());

      if ($path === 'readme') {
        $path .= '.remarkup';
      }

      if (strncmp($path, 'readme.', 7) !== 0) {
        continue;
      }

      $priority = 0;
      switch (substr($path, 7)) {
        case 'remarkup':
          $priority = 100;
          $render_type = 'remarkup';
          break;
        case 'rainbow':
          $priority = 90;
          $render_type = 'rainbow';
          break;
        case 'md':
          $priority = 50;
          $render_type = 'remarkup';
          break;
        case 'txt':
          $priority = 10;
          $render_type = 'plain';
          break;
        default:
          $priority = 0;
          $render_type = 'plain';
          break;
      }

      if ($priority > $best) {
        $best = $priority;
        $readme = $result_path;
        $best_render_type = $render_type;
      }
    }

    if (!$readme) {
      return '';
    }

    $readme_request = DiffusionRequest::newFromDictionary(
      array(
        'user' => $request->getUser(),
        'repository' => $drequest->getRepository(),
        'commit' => $drequest->getStableCommit(),
        'path' => $readme->getFullPath(),
      ));

    $file_content = DiffusionFileContent::newFromConduit(
      DiffusionQuery::callConduitWithDiffusionRequest(
        $request->getUser(),
        $readme_request,
        'diffusion.filecontentquery',
        array(
          'commit' => $drequest->getStableCommit(),
          'path' => $readme->getFullPath(),
          'needsBlame' => false,
        )));
    $readme_content = $file_content->getCorpus();

    switch ($best_render_type) {
      case 'plain':
        $readme_content = phutil_escape_html_newlines($readme_content);
        $class = null;
        break;
      case 'rainbow':
        $highlighter = new PhutilRainbowSyntaxHighlighter();
        $readme_content = $highlighter
          ->getHighlightFuture($readme_content)
          ->resolve();
        $readme_content = phutil_escape_html_newlines($readme_content);

        require_celerity_resource('syntax-highlighting-css');
        $class = 'remarkup-code';
        break;
      case 'remarkup':
        // TODO: This is sketchy, but make sure we hit the markup cache.
        $markup_object = id(new PhabricatorMarkupOneOff())
          ->setEngineRuleset('diffusion-readme')
          ->setContent($readme_content);
        $markup_field = 'default';

        $readme_content = id(new PhabricatorMarkupEngine())
          ->setViewer($request->getUser())
          ->addObject($markup_object, $markup_field)
          ->process()
          ->getOutput($markup_object, $markup_field);

        $engine = $markup_object->newMarkupEngine($markup_field);
        $toc = PhutilRemarkupHeaderBlockRule::renderTableOfContents($engine);
        if ($toc) {
          $toc = phutil_tag_div(
            'phabricator-remarkup-toc',
            array(
              phutil_tag_div(
                'phabricator-remarkup-toc-header',
                pht('Table of Contents')),
              $toc,
            ));
          $readme_content = array($toc, $readme_content);
        }

        $class = 'phabricator-remarkup';
        break;
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
