<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group phame
 */
final class PhameBasicTemplateBlogSkin extends PhameBasicBlogSkin {

  private $cssResources;

  public function processRequest() {
    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/support/phame/libskin.php';

    $css = $this->getPath('css/');
    if (Filesystem::pathExists($css)) {
      $this->cssResources = array();
      foreach (Filesystem::listDirectory($css) as $path) {
        if (!preg_match('/.css$/', $path)) {
          continue;
        }
        $this->cssResources[] = phutil_render_tag(
          'link',
          array(
            'rel'   => 'stylesheet',
            'type'  => 'text/css',
            'href'  => $this->getResourceURI('css/'.$path),
          ));
      }
      $this->cssResources = implode("\n", $this->cssResources);
    }

    $request = $this->getRequest();
    $content = $this->renderContent($request);

    if (!$content) {
      $content = $this->render404Page();
    }

    $content = array(
      $this->renderHeader(),
      $content,
      $this->renderFooter(),
    );

    $response = new AphrontWebpageResponse();
    $response->setContent(implode("\n", $content));

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

    return ob_get_clean();
  }

  private function getDefaultScope() {
    return array(
      'skin' => $this,
      'blog' => $this->getBlog(),
      'uri'  => $this->getURI(''),
    );
  }

  protected function renderHeader() {
    return $this->renderTemplate(
      'header.php',
      array(
        'title' => $this->getBlog()->getName(),
      ));
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
        'older' => $this->renderNewerPageLink(),
        'newer' => $this->renderOlderPageLink(),
      ));
  }

}
