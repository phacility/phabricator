<?php

/**
 * @group pholio
 */
final class PholioMockImagesView extends AphrontView {

  private $mock;
  private $imageID;
  private $requestURI;
  private $commentFormID;

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
      throw new Exception('Call setMock() before render()!');
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

    // TODO: We could maybe do a better job with tailoring this, which is the
    // image shown on the review stage.
    $nonimage_uri = celerity_get_resource_uri(
      'rsrc/image/icon/fatcow/thumbnails/default.p100.png');

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($this->getUser());
    foreach ($mock->getAllImages() as $image) {
      $engine->addObject($image, 'default');
    }
    $engine->process();
    $current_set = 0;
    foreach ($mock->getAllImages() as $image) {
      $file = $image->getFile();
      $metadata = $file->getMetadata();
      $x = idx($metadata, PhabricatorFile::METADATA_IMAGE_WIDTH);
      $y = idx($metadata, PhabricatorFile::METADATA_IMAGE_HEIGHT);

      $is_obs = (bool)$image->getIsObsolete();
      if (!$is_obs) {
        $current_set++;
      }

      $history_uri = '/pholio/image/history/'.$image->getID().'/';
      $images[] = array(
        'id' => $image->getID(),
        'fullURI' => $file->getBestURI(),
        'stageURI' => ($file->isViewableImage()
          ? $file->getBestURI()
          : $nonimage_uri),
        'pageURI' => $this->getImagePageURI($image, $mock),
        'downloadURI' => $file->getDownloadURI(),
        'historyURI' => $history_uri,
        'width' => $x,
        'height' => $y,
        'title' => $image->getName(),
        'descriptionMarkup' => $engine->getOutput($image, 'default'),
        'isObsolete' => (bool)$image->getIsObsolete(),
        'isImage' => $file->isViewableImage(),
        'isViewable' => $file->isViewableInBrowser(),
      );
    }

    $navsequence = array();
    foreach ($mock->getImages() as $image) {
      $navsequence[] = $image->getID();
    }

    $full_icon = array(
      javelin_tag('span', array('aural' => true), pht('View Raw File')),
      id(new PHUIIconView())->setIconFont('fa-file-image-o'),
    );

    $download_icon = array(
      javelin_tag('span', array('aural' => true), pht('Download File')),
      id(new PHUIIconView())->setIconFont('fa-download'),
    );

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
      'navsequence' => $navsequence,
      'fullIcon' => hsprintf('%s', $full_icon),
      'downloadIcon' => hsprintf('%s', $download_icon),
      'currentSetSize' => $current_set,
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

    $image_header = javelin_tag(
      'div',
      array(
        'id' => 'mock-image-header',
        'class' => 'pholio-mock-image-header',
      ),
      '');

    $mock_wrapper = javelin_tag(
      'div',
      array(
        'id' => $panel_id,
        'sigil' => 'mock-panel touchable',
        'class' => 'pholio-mock-image-panel',
      ),
      array(
        $image_header,
        $mock_wrapper,
      ));

    $inline_comments_holder = javelin_tag(
      'div',
      array(
        'id' => 'mock-image-description',
        'sigil' => 'mock-image-description',
        'class' => 'mock-image-description'
      ),
      '');

    $mockview[] = phutil_tag(
      'div',
        array(
          'class' => 'pholio-mock-image-container',
          'id' => 'pholio-mock-image-container'
        ),
      array($mock_wrapper, $inline_comments_holder));

    return $mockview;
  }

  private function getImagePageURI(PholioImage $image, PholioMock $mock) {
    $uri = '/M'.$mock->getID().'/'.$image->getID().'/';
    return $uri;
  }
}
