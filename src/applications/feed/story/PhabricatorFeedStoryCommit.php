<?php

final class PhabricatorFeedStoryCommit extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('commitPHID');
  }

  public function getRequiredHandlePHIDs() {
    return array(
      $this->getValue('committerPHID'),
    );
  }

  public function renderView() {
    $data = $this->getStoryData();

    $author = null;
    if ($data->getValue('authorPHID')) {
      $author = $this->linkTo($data->getValue('authorPHID'));
    } else {
      $author = phutil_escape_html($data->getValue('authorName'));
    }

    $committer = null;
    if ($data->getValue('committerPHID')) {
      $committer = $this->linkTo($data->getValue('committerPHID'));
    } else if ($data->getValue('committerName')) {
      $committer = phutil_escape_html($data->getValue('committerName'));
    }

    $commit = $this->linkTo($data->getValue('commitPHID'));

    if (!$committer) {
      $committer = $author;
      $author = null;
    }

    if ($author) {
      $title = "{$committer} committed {$commit} (authored by {$author})";
    } else {
      $title = "{$committer} committed {$commit}";
    }

    $view = new PhabricatorFeedStoryView();

    $view->setTitle($title);
    $view->setEpoch($data->getEpoch());

    if ($data->getValue('authorPHID')) {
      $view->setImage($this->getHandle($data->getAuthorPHID())->getImageURI());
    }

    $content = $this->renderSummary($data->getValue('summary'));
    $view->appendChild($content);

    return $view;
  }

}
