<?php

/**
 * @group pholio
 */
final class PholioMockEmbedView extends AphrontView {

  private $mock;
  private $images = array();

  public function setMock(PholioMock $mock) {
    $this->mock = $mock;
    return $this;
  }

  public function setImages(array $images) {
    $this->images = $images;
    return $this;
  }

  public function render() {
    if (!$this->mock) {
      throw new Exception('Call setMock() before render()!');
    }
    $mock = $this->mock;

    $images_to_show = array();
    $thumbnail = null;
    if (!empty($this->images)) {
      $images_to_show = array_intersect_key(
        $this->mock->getImages(), array_flip($this->images));
      foreach ($images_to_show as $image) {
        $thumbfile = $image->getFile();
        $thumbnail = $thumbfile->getThumb280x210URI();
      }
      $header = 'M'.$mock->getID().' '.$mock->getName().
        ' (#'.$image->getID().')';
      $uri = '/M'.$this->mock->getID().'/'.$image->getID().'/';
    } else {
      $thumbnail = $mock->getCoverFile()->getThumb280x210URI();
      $header = 'M'.$mock->getID().' '.$mock->getName();
      $uri = '/M'.$this->mock->getID();
    }

    $item = id(new PHUIPinboardItemView())
      ->setHeader($header)
      ->setURI($uri)
      ->setImageURI($thumbnail)
      ->setImageSize(280, 210)
      ->setDisabled($mock->isClosed())
      ->addIconCount('fa-picture-o', count($mock->getImages()))
      ->addIconCount('fa-trophy', $mock->getTokenCount());

    return $item;
  }
}
