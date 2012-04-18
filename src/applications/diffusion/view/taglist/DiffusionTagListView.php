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

final class DiffusionTagListView extends DiffusionView {

  private $tags;
  private $user;
  private $commits = array();
  private $handles = array();

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function setTags($tags) {
    $this->tags = $tags;
    return $this;
  }

  public function setCommits(array $commits) {
    $this->commits = mpull($commits, null, 'getCommitIdentifier');
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function getRequiredHandlePHIDs() {
    return array_filter(mpull($this->commits, 'getAuthorPHID'));
  }

  public function render() {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();


    $rows = array();
    foreach ($this->tags as $tag) {
      $commit = idx($this->commits, $tag->getCommitIdentifier());

      $tag_link = phutil_render_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action' => 'browse',
              'commit' => $tag->getCommitIdentifier(),
            )),
        ),
        phutil_escape_html($tag->getName()));

      $commit_link = phutil_render_tag(
        'a',
        array(
          'href' => $drequest->generateURI(
            array(
              'action' => 'commit',
              'commit' => $tag->getCommitIdentifier(),
            )),
        ),
        phutil_escape_html(
          $repository->formatCommitName(
            $tag->getCommitIdentifier())));

      $author = null;
      if ($commit && $commit->getAuthorPHID()) {
        $author = $this->handles[$commit->getAuthorPHID()]->renderLink();
      } else if ($commit && $commit->getCommitData()) {
        $author = phutil_escape_html($commit->getCommitData()->getAuthorName());
      } else {
        $author = phutil_escape_html($tag->getAuthor());
      }

      $description = null;
      if ($tag->getType() == 'git/tag') {
        // In Git, a tag may be a "real" tag, or just a reference to a commit.
        // If it's a real tag, use the message on the tag, since this may be
        // unique data which isn't otherwise available.
        $description = $tag->getDescription();
      } else {
        if ($commit && $commit->getCommitData()) {
          $description = $commit->getCommitData()->getSummary();
        } else {
          $description = $tag->getDescription();
        }
      }
      $description = phutil_escape_html($description);

      $rows[] = array(
        $tag_link,
        $commit_link,
        $description,
        $author,
        phabricator_datetime($tag->getEpoch(), $this->user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Tag',
        'Commit',
        'Description',
        'Author',
        'Created',
      ));
    $table->setColumnClasses(
      array(
        'pri',
        '',
        'wide',
      ));
    return $table->render();
  }

}
