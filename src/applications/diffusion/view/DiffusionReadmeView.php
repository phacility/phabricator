<?php

final class DiffusionReadmeView extends DiffusionView {

  private $path;
  private $content;

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getPath() {
    return $this->path;
  }

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function getContent() {
    return $this->content;
  }

  /**
   * Get the markup language a README should be interpreted as.
   *
   * @param string Local README path, like "README.txt".
   * @return string Best markup interpreter (like "remarkup") for this file.
   */
   private function getReadmeLanguage($path) {
    $path = phutil_utf8_strtolower($path);
    if ($path == 'readme') {
      return 'remarkup';
    }

    $ext = last(explode('.', $path));
    switch ($ext) {
      case 'remarkup':
      case 'md':
        return 'remarkup';
      case 'rainbow':
        return 'rainbow';
      case 'txt':
      default:
        return 'text';
    }
  }


  public function render() {
    $readme_path = $this->getPath();
    $readme_name = basename($readme_path);
    $interpreter = $this->getReadmeLanguage($readme_name);
    require_celerity_resource('diffusion-readme-css');

    $content = $this->getContent();

    $class = null;
    switch ($interpreter) {
      case 'remarkup':
        // TODO: This is sketchy, but make sure we hit the markup cache.
        $markup_object = id(new PhabricatorMarkupOneOff())
          ->setEngineRuleset('diffusion-readme')
          ->setContent($content);
        $markup_field = 'default';

        $content = id(new PhabricatorMarkupEngine())
          ->setViewer($this->getUser())
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
          $content = array($toc, $content);
        }

        $readme_content = $content;
        $class = null;
        break;
      case 'rainbow':
        $content = id(new PhutilRainbowSyntaxHighlighter())
          ->getHighlightFuture($content)
          ->resolve();
        $readme_content = phutil_escape_html_newlines($content);

        require_celerity_resource('syntax-highlighting-css');
        $class = 'remarkup-code ml';
        break;
      default:
      case 'text':
        $readme_content = phutil_escape_html_newlines($content);
        $class = 'ml';
        break;
    }

    $readme_content = phutil_tag_div($class, $readme_content);
    $header = id(new PHUIHeaderView())
      ->setHeader($readme_name);

    return id(new PHUIDocumentView())
      ->setFluid(true)
      ->appendChild($readme_content)
      ->addClass('diffusion-readme-view')
      ->setHeader($header)
      ->setFontKit(PHUIDocumentView::FONT_SOURCE_SANS);
  }

}
