<?php

final class DifferentialLocalCommitsView extends AphrontView {

  private $localCommits;
  private $user;

  public function setLocalCommits($local_commits) {
    $this->localCommits = $local_commits;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function render() {
    $user = $this->user;
    if (!$user) {
      throw new Exception("Call setUser() before render()-ing this view.");
    }

    $local = $this->localCommits;
    if (!$local) {
      return null;
    }

    require_celerity_resource('differential-local-commits-view-css');

    $has_tree = false;
    $has_local = false;

    foreach ($local as $commit) {
      if (idx($commit, 'tree')) {
        $has_tree = true;
      }
      if (idx($commit, 'local')) {
        $has_local = true;
      }
    }

    $rows = array();
    foreach ($local as $commit) {

      $row = array();
      if (idx($commit, 'commit')) {
        $commit_hash = substr($commit['commit'], 0, 16);
      } else if (isset($commit['rev'])) {
        $commit_hash = substr($commit['rev'], 0, 16);
      } else {
        $commit_hash = null;
      }
      $row[] = '<td>'.phutil_escape_html($commit_hash).'</td>';

      if ($has_tree) {
        $tree = idx($commit, 'tree');
        $tree = substr($tree, 0, 16);
        $row[] = '<td>'.phutil_escape_html($tree).'</td>';
      }

      if ($has_local) {
        $local_rev = idx($commit, 'local', null);
        $row[] = '<td>'.phutil_escape_html($local_rev).'</td>';
      }

      $parents = idx($commit, 'parents', array());
      foreach ($parents as $k => $parent) {
        if (is_array($parent)) {
          $parent = idx($parent, 'rev');
        }
        $parents[$k] = phutil_escape_html(substr($parent, 0, 16));
      }
      $parents = implode('<br />', $parents);
      $row[] = '<td>'.$parents.'</td>';

      $author = nonempty(
        idx($commit, 'user'),
        idx($commit, 'author'));
      $row[] = '<td>'.phutil_escape_html($author).'</td>';

      $message = idx($commit, 'message');

      $summary = idx($commit, 'summary');
      $summary = phutil_utf8_shorten($summary, 80);

      $view = new AphrontMoreView();
      $view->setSome(phutil_escape_html($summary));

      if ($message && (trim($summary) != trim($message))) {
        $view->setMore(nl2br(phutil_escape_html($message)));
      }

      $row[] = phutil_render_tag(
        'td',
        array(
          'class' => 'summary',
        ),
        $view->render());

      $date = nonempty(
        idx($commit, 'date'),
        idx($commit, 'time'));
      if ($date) {
        $date = phabricator_datetime($date, $user);
      }
      $row[] = '<td>'.$date.'</td>';

      $rows[] = '<tr>'.implode('', $row).'</tr>';
    }


    $headers = array();
    $headers[] = '<th>Commit</th>';
    if ($has_tree) {
      $headers[] = '<th>Tree</th>';
    }
    if ($has_local) {
      $headers[] = '<th>Local</th>';
    }
    $headers[] = '<th>Parents</th>';
    $headers[] = '<th>Author</th>';
    $headers[] = '<th>Summary</th>';
    $headers[] = '<th>Date</th>';

    $headers = '<tr>'.implode('', $headers).'</tr>';

    return
      '<div class="differential-panel">'.
        '<h1>Local Commits</h1>'.
        '<table class="differential-local-commits-table">'.
          $headers.
          implode("\n", $rows).
        '</table>'.
      '</div>';
  }
}
