<?php

final class PhabricatorFeedStoryProject extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('projectPHID');
  }

  public function renderView() {
    $data = $this->getStoryData();

    $view = new PhabricatorFeedStoryView();

    $type = $data->getValue('type');
    $old = $data->getValue('old');
    $new = $data->getValue('new');
    $proj_phid = $data->getValue('projectPHID');

    $author_phid = $data->getAuthorPHID();

    switch ($type) {
      case PhabricatorProjectTransactionType::TYPE_NAME:
        if (strlen($old)) {
          $action = 'renamed project '.
            $this->linkTo($proj_phid).
            ' from '.
            $this->renderString($old).
            ' to '.
            $this->renderString($new).
            '.';
        } else {
          $action = 'created project '.
            $this->linkTo($proj_phid).
            ' (as '.
            $this->renderString($new).
            ').';
        }
        break;
      case PhabricatorProjectTransactionType::TYPE_STATUS:
        $action = 'changed project '.
                  $this->linkTo($proj_phid).
                  ' status from '.
                  $this->renderString(
                    PhabricatorProjectStatus::getNameForStatus($old)).
                  ' to '.
                  $this->renderString(
                    PhabricatorProjectStatus::getNameForStatus($new)).
                  '.';
        break;
      case PhabricatorProjectTransactionType::TYPE_MEMBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ((count($add) == 1) && (count($rem) == 0) &&
            (head($add) == $author_phid)) {
          $action = 'joined project '.$this->linkTo($proj_phid).'.';
        } else if ((count($add) == 0) && (count($rem) == 1) &&
                   (head($rem) == $author_phid)) {
          $action = 'left project '.$this->linkTo($proj_phid).'.';
        } else if (empty($rem)) {
          $action = 'added members to project '.
            $this->linkTo($proj_phid).': '.
            $this->renderHandleList($add).'.';
        } else if (empty($add)) {
          $action = 'removed members from project '.
            $this->linkTo($proj_phid).': '.
            $this->renderHandleList($rem).'.';
        } else {
          $action = 'changed members of project '.
            $this->linkTo($proj_phid).', added: '.
            $this->renderHandleList($add).'; removed: '.
            $this->renderHandleList($rem).'.';
        }
        break;
      default:
        $action = 'updated project '.$this->linkTo($proj_phid).'.';
        break;
    }
    $view->setTitle($this->linkTo($author_phid).' '.$action);
    $view->setOneLineStory(true);

    return $view;
  }

  public function renderText() {
    $type = $this->getValue('type');
    $old = $this->getValue('old');
    $new = $this->getValue('new');

    $proj_handle = $this->getHandle($this->getPrimaryObjectPHID());
    $proj_name = $proj_handle->getLinkName();
    $proj_uri = PhabricatorEnv::getURI($proj_handle->getURI());

    $author_phid = $this->getAuthorPHID();
    $author_name = $this->getHandle($author_phid)->getLinkName();

    switch ($type) {
      case PhabricatorProjectTransactionType::TYPE_NAME:
        if (strlen($old)) {
          $action = 'renamed project '.
            $proj_name.
            ' from '.
            $old.
            ' to '.
            $new;
        } else {
          $action = 'created project '.
            $proj_name.
            ' (as '.
            $new.
            ')';
        }
        break;
      case PhabricatorProjectTransactionType::TYPE_STATUS:
        $action = 'changed project '.
                  $proj_name.
                  ' status from '.
                  $old.
                  ' to '.
                  $new;
        break;
      case PhabricatorProjectTransactionType::TYPE_MEMBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ((count($add) == 1) && (count($rem) == 0) &&
            (head($add) == $author_phid)) {
          $action = 'joined project';
        } else if ((count($add) == 0) && (count($rem) == 1) &&
                   (head($rem) == $author_phid)) {
          $action = 'left project';
        } else if (empty($rem)) {
          $action = 'added members to project';
        } else if (empty($add)) {
          $action = 'removed members from project';
        } else {
          $action = 'changed members of project';
        }
        $action .= " {$proj_name}";
        break;
      default:
        $action = "updated project {$proj_name}";
        break;
    }

    $text = "{$author_name} {$action} {$proj_uri}";

    return $text;
  }

}
