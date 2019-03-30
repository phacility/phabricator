<?php

final class PhabricatorProjectColumnAuthorOrder
  extends PhabricatorProjectColumnOrder {

  const ORDERKEY = 'author';

  public function getDisplayName() {
    return pht('Group by Author');
  }

  protected function newMenuIconIcon() {
    return 'fa-user-plus';
  }

  public function getHasHeaders() {
    return true;
  }

  public function getCanReorder() {
    return false;
  }

  public function getMenuOrder() {
    return 3000;
  }

  protected function newHeaderKeyForObject($object) {
    return $this->newHeaderKeyForAuthorPHID($object->getAuthorPHID());
  }

  private function newHeaderKeyForAuthorPHID($author_phid) {
    return sprintf('author(%s)', $author_phid);
  }

  protected function newSortVectorsForObjects(array $objects) {
    $author_phids = mpull($objects, null, 'getAuthorPHID');
    $author_phids = array_keys($author_phids);
    $author_phids = array_filter($author_phids);

    if ($author_phids) {
      $author_users = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($author_phids)
        ->execute();
      $author_users = mpull($author_users, null, 'getPHID');
    } else {
      $author_users = array();
    }

    $vectors = array();
    foreach ($objects as $vector_key => $object) {
      $author_phid = $object->getAuthorPHID();
      $author = idx($author_users, $author_phid);
      if ($author) {
        $vector = $this->newSortVectorForAuthor($author);
      } else {
        $vector = $this->newSortVectorForAuthorPHID($author_phid);
      }

      $vectors[$vector_key] = $vector;
    }

    return $vectors;
  }

  private function newSortVectorForAuthor(PhabricatorUser $user) {
    return array(
      1,
      $user->getUsername(),
    );
  }

  private function newSortVectorForAuthorPHID($author_phid) {
    return array(
      2,
      $author_phid,
    );
  }

  protected function newHeadersForObjects(array $objects) {
    $author_phids = mpull($objects, null, 'getAuthorPHID');
    $author_phids = array_keys($author_phids);
    $author_phids = array_filter($author_phids);

    if ($author_phids) {
      $author_users = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($author_phids)
        ->needProfileImage(true)
        ->execute();
      $author_users = mpull($author_users, null, 'getPHID');
    } else {
      $author_users = array();
    }

    $headers = array();
    foreach ($author_phids as $author_phid) {
      $header_key = $this->newHeaderKeyForAuthorPHID($author_phid);

      $author = idx($author_users, $author_phid);
      if ($author) {
        $sort_vector = $this->newSortVectorForAuthor($author);
        $author_name = $author->getUsername();
        $author_image = $author->getProfileImageURI();
      } else {
        $sort_vector = $this->newSortVectorForAuthorPHID($author_phid);
        $author_name = pht('Unknown User ("%s")', $author_phid);
        $author_image = null;
      }

      $author_icon = 'fa-user';
      $author_color = 'bluegrey';

      $icon_view = id(new PHUIIconView());

      if ($author_image) {
        $icon_view->setImage($author_image);
      } else {
        $icon_view->setIcon($author_icon, $author_color);
      }

      $header = $this->newHeader()
        ->setHeaderKey($header_key)
        ->setSortVector($sort_vector)
        ->setName($author_name)
        ->setIcon($icon_view)
        ->setEditProperties(
          array(
            'value' => $author_phid,
          ));

      $headers[] = $header;
    }

    return $headers;
  }

}
