<?php

final class PhameBasicTemplateBlogSkin extends PhameBasicBlogSkin {

  private $cssResources;

  public function processRequest() {
    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/support/phame/libskin.php';

    $this->cssResources = array();
    $css = $this->getPath('css/');

    if (Filesystem::pathExists($css)) {
      foreach (Filesystem::listDirectory($css) as $path) {
        if (!preg_match('/.css$/', $path)) {
          continue;
        }
        $this->cssResources[] = phutil_tag(
          'link',
          array(
            'rel'   => 'stylesheet',
            'type'  => 'text/css',
            'href'  => $this->getResourceURI('css/'.$path),
          ));
      }
    }

    $map = CelerityResourceMap::getNamedInstance('phabricator');
    $resource_symbol = 'syntax-highlighting-css';
    $resource_uri = $map->getURIForSymbol($resource_symbol);

    $this->cssResources[] = phutil_tag(
      'link',
      array(
        'rel'   => 'stylesheet',
        'type'  => 'text/css',
        'href'  => PhabricatorEnv::getCDNURI($resource_uri),
      ));

    $this->cssResources = phutil_implode_html("\n", $this->cssResources);

    $request = $this->getRequest();

    // Render page parts in order so the templates execute in order, if we're
    // using templates.
    $header = $this->renderHeader();
    $content = $this->renderContent($request);
    $footer = $this->renderFooter();

    if (!$content) {
      $content = $this->render404Page();
    }

    $content = array(
      $header,
      $content,
      $footer,
    );

    $response = new AphrontWebpageResponse();
    $response->setContent(phutil_implode_html("\n", $content));

    return $response;
  }

  public function getCSSResources() {
    return $this->cssResources;
  }

  public function getName() {
    return $this->getSpecification()->getName();
  }

  public function getPath($to_file = null) {
    $path = $this->getSpecification()->getRootDirectory();
    if ($to_file) {
      $path = $path.DIRECTORY_SEPARATOR.$to_file;
    }
    return $path;
  }

  private function renderTemplate($__template__, array $__scope__) {
    chdir($this->getPath());
    ob_start();

    if (Filesystem::pathExists($this->getPath($__template__))) {
      // Fool lint.
      $__evil__ = 'extract';
      $__evil__($__scope__ + $this->getDefaultScope());
      require $this->getPath($__template__);
    }

    return phutil_safe_html(ob_get_clean());
  }

  private function getDefaultScope() {
    return array(
      'skin'        => $this,
      'blog'        => $this->getBlog(),
      'uri'         => $this->getURI($this->getURIPath()),
      'home_uri'    => $this->getURI(''),
      'title'       => $this->getTitle(),
      'description' => $this->getDescription(),
      'og_type'     => $this->getOGType(),
    );
  }

  protected function renderHeader() {
    return $this->renderTemplate(
      'header.php',
      array());
  }

  protected function renderFooter() {
    return $this->renderTemplate('footer.php', array());
  }

  protected function render404Page() {
    return $this->renderTemplate('404.php', array());
  }

  protected function renderPostDetail(PhamePostView $post) {
    return $this->renderTemplate(
      'post-detail.php',
      array(
        'post'  => $post,
      ));
  }

  protected function renderPostList(array $posts) {
    return $this->renderTemplate(
      'post-list.php',
      array(
        'posts' => $posts,
        'older' => $this->renderOlderPageLink(),
        'newer' => $this->renderNewerPageLink(),
      ));
  }

}
