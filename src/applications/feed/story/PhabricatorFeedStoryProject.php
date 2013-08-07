<?php

final class PhabricatorFeedStoryProject extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getValue('projectPHID');
  }

  public function getRequiredHandlePHIDs() {
    $req_phids = array();
    $data = $this->getStoryData();
    switch ($data->getValue('type')) {
      case PhabricatorProjectTransactionType::TYPE_MEMBERS:
          $old = $data->getValue('old');
          $new = $data->getValue('new');
          $add = array_diff($new, $old);
          $rem = array_diff($old, $new);
          $req_phids = array_merge($add, $rem);
        break;
    }
    return array_merge($req_phids, parent::getRequiredHandlePHIDs());
  }

  public function renderView() {
    $data = $this->getStoryData();

    $view = $this->newStoryView();
    $view->setAppIcon('projects-dark');

    $type = $data->getValue('type');
    $old = $data->getValue('old');
    $new = $data->getValue('new');
    $proj_phid = $data->getValue('projectPHID');

    $author_phid = $data->getAuthorPHID();
    $author_link = $this->linkTo($author_phid);

    switch ($type) {
      case PhabricatorProjectTransactionType::TYPE_NAME:
        if (strlen($old)) {
          $action = pht(
            '%s renamed project %s from %s to %s.',
            $author_link,
            $this->linkTo($proj_phid),
            $this->renderString($old),
            $this->renderString($new));
        } else {
          $action = pht(
            '%s created project %s (as %s).',
            $author_link,
            $this->linkTo($proj_phid),
            $this->renderString($new));
        }
        break;
      case PhabricatorProjectTransactionType::TYPE_STATUS:
        $old_name = PhabricatorProjectStatus::getNameForStatus($old);
        $new_name = PhabricatorProjectStatus::getNameForStatus($new);
        $action = pht(
          '%s changed project %s status from %s to %s.',
          $author_link,
          $this->linkTo($proj_phid),
          $this->renderString($old_name),
          $this->renderString($new_name));
        break;
      case PhabricatorProjectTransactionType::TYPE_MEMBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ((count($add) == 1) && (count($rem) == 0) &&
            (head($add) == $author_phid)) {
          $action = pht(
            '%s joined project %s.',
            $author_link,
            $this->linkTo($proj_phid));
        } else if ((count($add) == 0) && (count($rem) == 1) &&
                   (head($rem) == $author_phid)) {
          $action = pht(
            '%s left project %s.',
            $author_link,
            $this->linkTo($proj_phid));
        } else if (empty($rem)) {
          $action = pht(
            '%s added members to project %s: %s.',
            $author_link,
            $this->linkTo($proj_phid),
            $this->renderHandleList($add));
        } else if (empty($add)) {
          $action = pht(
            '%s removed members from project %s: %s.',
            $author_link,
            $this->linkTo($proj_phid),
            $this->renderHandleList($rem));
        } else {
          $action = pht(
            '%s changed members of project %s, added: %s; removed: %s.',
            $author_link,
            $this->linkTo($proj_phid),
            $this->renderHandleList($add),
            $this->renderHandleList($rem));
        }
        break;
      case PhabricatorProjectTransactionType::TYPE_CAN_VIEW:
        $action = pht(
          '%s changed the visibility for %s.',
          $author_link,
          $this->linkTo($proj_phid));
        break;
      case PhabricatorProjectTransactionType::TYPE_CAN_EDIT:
        $action = pht(
          '%s changed the edit policy for %s.',
          $author_link,
          $this->linkTo($proj_phid));
        break;
      case PhabricatorProjectTransactionType::TYPE_CAN_JOIN:
        $action = pht(
          '%s changed the join policy for %s.',
          $author_link,
          $this->linkTo($proj_phid));
        break;
      default:
        $action = pht(
          '%s updated project %s.',
          $author_link,
          $this->linkTo($proj_phid));
        break;
    }

    $view->setTitle($action);
    $view->setImage($this->getHandle($author_phid)->getImageURI());

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
    $author_name = $this->linkTo($author_phid);

    switch ($type) {
      case PhabricatorProjectTransactionType::TYPE_NAME:
        if (strlen($old)) {
          $text =
            pht('%s renamed project %s from %s to %s %s',
              $author_name,
              $proj_name,
              $old,
              $new,
              $proj_uri);
        } else {
          $text =
            pht('%s created project %s (as %s) %s',
              $author_name,
              $proj_name,
              $new,
              $proj_uri);
        }
        break;
      case PhabricatorProjectTransactionType::TYPE_STATUS:
        $text =
          pht('%s changed project %s status from %s to %s %s',
            $author_name,
            $proj_name,
            $old,
            $new,
            $proj_uri);
        break;
      case PhabricatorProjectTransactionType::TYPE_MEMBERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ((count($add) == 1) && (count($rem) == 0) &&
            (head($add) == $author_phid)) {
          $text =
            pht('%s joined project %s %s',
              $author_name,
              $proj_name,
              $proj_uri);
        } else if ((count($add) == 0) && (count($rem) == 1) &&
                   (head($rem) == $author_phid)) {
          $text =
            pht('%s left project %s %s',
              $author_name,
              $proj_name,
              $proj_uri);
        } else if (empty($rem)) {
          $text =
            pht('%s added members to project %s %s',
              $author_name,
              $proj_name,
              $proj_uri);
        } else if (empty($add)) {
          $text =
            pht('%s removed members from project %s %s',
              $author_name,
              $proj_name,
              $proj_uri);
        } else {
          $text =
            pht('%s changed members of project %s %s',
              $author_name,
              $proj_name,
              $proj_uri);
        }
        break;
      default:
          $text =
            pht('%s updated project %s %s',
              $author_name,
              $proj_name,
              $proj_uri);
        break;
    }

    return $text;
  }

}
