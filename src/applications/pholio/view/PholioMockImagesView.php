<?php

/**
 * @group pholio
 */
final class PholioMockImagesView extends AphrontView {

  private $mock;
  private $imageID;
  private $requestURI;
  private $commentFormID;
  private $viewMode = 'normal';

  /**
   * Supports normal (/MX, /MX/Y/) and history (/pholio/image/history/Y/)
   * modes. The former has handy dandy commenting functionality and the
   * latter does not.
   */
  public function setViewMode($view_mode) {
    $this->viewMode = $view_mode;
    return $this;
  }
  public function getViewMode() {
    return $this->viewMode;
  }

  public function setCommentFormID($comment_form_id) {
    $this->commentFormID = $comment_form_id;
    return $this;
  }
  public function getCommentFormID() {
    return $this->commentFormID;
  }

  public function setRequestURI(PhutilURI $request_uri) {
    $this->requestURI = $request_uri;
    return $this;
  }
  public function getRequestURI() {
    return $this->requestURI;
  }

  public function setImageID($image_id) {
    $this->imageID = $image_id;
    return $this;
  }

  public function getImageID() {
    return $this->imageID;
  }

  public function setMock(PholioMock $mock) {
    $this->mock = $mock;
    return $this;
  }

  public function render() {
    if (!$this->mock) {
      throw new Exception("Call setMock() before render()!");
    }

    $mock = $this->mock;

    require_celerity_resource('javelin-behavior-pholio-mock-view');

    $images = array();
    $panel_id = celerity_generate_unique_node_id();
    $viewport_id = celerity_generate_unique_node_id();

    $ids = mpull($mock->getImages(), 'getID');
    if ($this->imageID && isset($ids[$this->imageID])) {
      $selected_id = $this->imageID;
    } else {
      $selected_id = head_key($ids);
    }

    foreach ($mock->getImages() as $image) {
      $file = $image->getFile();
      $metadata = $file->getMetadata();
      $x = idx($metadata, PhabricatorFile::METADATA_IMAGE_WIDTH);
      $y = idx($metadata, PhabricatorFile::METADATA_IMAGE_HEIGHT);

      $history_uri = '/pholio/image/history/'.$image->getID().'/';
      $images[] = array(
        'id' => $image->getID(),
        'fullURI' => $file->getBestURI(),
        'pageURI' => $this->getImagePageURI($image, $mock),
        'historyURI' => $history_uri,
        'width' => $x,
        'height' => $y,
        'title' => $image->getName(),
        'desc' => $image->getDescription(),
        'isObsolete' => (bool) $image->getIsObsolete(),
      );
    }

    $login_uri = id(new PhutilURI('/login/'))
      ->setQueryParam('next', (string) $this->getRequestURI());
    $config = array(
      'mockID' => $mock->getID(),
      'panelID' => $panel_id,
      'viewportID' => $viewport_id,
      'commentFormID' => $this->getCommentFormID(),
      'images' => $images,
      'selectedID' => $selected_id,
      'loggedIn' => $this->getUser()->isLoggedIn(),
      'logInLink' => (string) $login_uri,
      'viewMode' => $this->getViewMode()
    );
    Javelin::initBehavior('pholio-mock-view', $config);

    $mockview = '';

    $mock_wrapper = javelin_tag(
      'div',
      array(
        'id' => $viewport_id,
        'sigil' => 'mock-viewport',
        'class' => 'pholio-mock-image-viewport'
      ),
      '');

    $mock_wrapper = javelin_tag(
      'div',
      array(
        'id' => $panel_id,
        'sigil' => 'mock-panel touchable',
        'class' => 'pholio-mock-image-panel',
      ),
      $mock_wrapper);

    $inline_comments_holder = javelin_tag(
      'div',
      array(
        'id' => 'mock-inline-comments',
        'sigil' => 'mock-inline-comments',
        'class' => 'pholio-mock-inline-comments'
      ),
      '');

    $carousel_holder = '';
    if (count($mock->getImages()) > 0) {
      $thumbnails = array();
      foreach ($mock->getImages() as $image) {
        $thumbfile = $image->getFile();

        $dimensions = PhabricatorImageTransformer::getPreviewDimensions(
          $thumbfile,
          140);

        $tag = phutil_tag(
          'img',
          array(
            'width' => $dimensions['sdx'],
            'height' => $dimensions['sdy'],
            'src' => $thumbfile->getPreview140URI(),
            'class' => 'pholio-mock-carousel-thumbnail',
            'style' => 'top: '.floor((140 - $dimensions['sdy'] ) / 2).'px',
        ));

        $thumbnails[] = javelin_tag(
          'a',
          array(
            'sigil' => 'mock-thumbnail',
            'class' => 'pholio-mock-carousel-thumb-item',
            'href' => $this->getImagePageURI($image, $mock),
            'meta' => array(
              'imageID' => $image->getID(),
            ),
          ),
          $tag);
      }

      $carousel_holder = phutil_tag(
        'div',
        array(
          'id' => 'pholio-mock-carousel',
          'class' => 'pholio-mock-carousel',
        ),
        $thumbnails);
    }

    $mockview[] = phutil_tag(
      'div',
        array(
          'class' => 'pholio-mock-image-container',
          'id' => 'pholio-mock-image-container'
        ),
      array($mock_wrapper, $carousel_holder, $inline_comments_holder));

    return $mockview;
  }

  private function getImagePageURI(PholioImage $image, PholioMock $mock) {
    if ($this->getViewMode() == 'normal') {
      $uri = '/M'.$mock->getID().'/'.$image->getID().'/';
    } else {
      $uri = '/pholio/image/history/'.$image->getID().'/';
    }
    return $uri;
  }
}
