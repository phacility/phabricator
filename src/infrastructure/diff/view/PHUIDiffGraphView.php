<?php

final class PHUIDiffGraphView extends Phobject {

  private $isHead = true;
  private $isTail = true;

  public function setIsHead($is_head) {
    $this->isHead = $is_head;
    return $this;
  }

  public function getIsHead() {
    return $this->isHead;
  }

  public function setIsTail($is_tail) {
    $this->isTail = $is_tail;
    return $this;
  }

  public function getIsTail() {
    return $this->isTail;
  }

  public function renderRawGraph(array $parents) {
    // This keeps our accumulated information about each line of the
    // merge/branch graph.
    $graph = array();

    // This holds the next commit we're looking for in each column of the
    // graph.
    $threads = array();

    // This is the largest number of columns any row has, i.e. the width of
    // the graph.
    $count = 0;

    foreach ($parents as $cursor => $parent_list) {
      $joins = array();
      $splits = array();

      // Look for some thread which has this commit as the next commit. If
      // we find one, this commit goes on that thread. Otherwise, this commit
      // goes on a new thread.

      $line = '';
      $found = false;
      $pos = count($threads);

      $thread_count = $pos;
      for ($n = 0; $n < $thread_count; $n++) {
        if (empty($threads[$n])) {
          $line .= ' ';
          continue;
        }

        if ($threads[$n] == $cursor) {
          if ($found) {
            $line .= ' ';
            $joins[] = $n;
            $threads[$n] = false;
          } else {
            $line .= 'o';
            $found = true;
            $pos = $n;
          }
        } else {

          // We render a "|" for any threads which have a commit that we haven't
          // seen yet, this is later drawn as a vertical line.
          $line .= '|';
        }
      }

      // If we didn't find the thread this commit goes on, start a new thread.
      // We use "o" to mark the commit for the rendering engine, or "^" to
      // indicate that there's nothing after it so the line from the commit
      // upward should not be drawn.

      if (!$found) {
        if ($this->getIsHead()) {
          $line .= '^';
        } else {
          $line .= 'o';
          foreach ($graph as $k => $meta) {
            // Go back across all the lines we've already drawn and add a
            // "|" to the end, since this is connected to some future commit
            // we don't know about.
            for ($jj = strlen($meta['line']); $jj <= $count; $jj++) {
              $graph[$k]['line'] .= '|';
            }
          }
        }
      }

      // Update the next commit on this thread to the commit's first parent.
      // This might have the effect of making a new thread.
      $threads[$pos] = head($parent_list);

      // If we made a new thread, increase the thread count.
      $count = max($pos + 1, $count);

      // Now, deal with splits (merges). I picked this terms opposite to the
      // underlying repository term to confuse you.
      foreach (array_slice($parent_list, 1) as $parent) {
        $found = false;

        // Try to find the other parent(s) in our existing threads. If we find
        // them, split to that thread.

        foreach ($threads as $idx => $thread_commit) {
          if ($thread_commit == $parent) {
            $found = true;
            $splits[] = $idx;
            break;
          }
        }

        // If we didn't find the parent, we don't know about it yet. Find the
        // first free thread and add it as the "next" commit in that thread.
        // This might create a new thread.

        if (!$found) {
          for ($n = 0; $n < $count; $n++) {
            if (empty($threads[$n])) {
              break;
            }
          }
          $threads[$n] = $parent;
          $splits[] = $n;
          $count = max($n + 1, $count);
        }
      }

      $graph[] = array(
        'line' => $line,
        'split' => $splits,
        'join' => $joins,
      );
    }

    // If this is the last page in history, replace any "o" characters at the
    // bottom of columns with "x" characters so we do not draw a connecting
    // line downward, and replace "^" with an "X" for repositories with
    // exactly one commit.
    if ($this->getIsTail() && $graph) {
      $terminated = array();
      foreach (array_reverse(array_keys($graph)) as $key) {
        $line = $graph[$key]['line'];
        $len = strlen($line);
        for ($ii = 0; $ii < $len; $ii++) {
          $c = $line[$ii];
          if ($c == 'o') {
            // If we've already terminated this thread, we don't need to add
            // a terminator.
            if (isset($terminated[$ii])) {
              continue;
            }

            $terminated[$ii] = true;

            // If this thread is joining some other node here, we don't want
            // to terminate it.
            if (isset($graph[$key + 1])) {
              $joins = $graph[$key + 1]['join'];
              if (in_array($ii, $joins)) {
                continue;
              }
            }

            $graph[$key]['line'][$ii] = 'x';
          } else if ($c != ' ') {
            $terminated[$ii] = true;
          } else {
            unset($terminated[$ii]);
          }
        }
      }

      $last = array_pop($graph);
      $last['line'] = str_replace('^', 'X', $last['line']);
      $graph[] = $last;
    }

    return array($graph, $count);
  }

  public function renderGraph(array $parents) {
    list($graph, $count) = $this->renderRawGraph($parents);

    // Render into tags for the behavior.

    foreach ($graph as $k => $meta) {
      $graph[$k] = javelin_tag(
        'div',
        array(
          'sigil' => 'commit-graph',
          'meta' => $meta,
        ),
        '');
    }

    Javelin::initBehavior(
      'diffusion-commit-graph',
      array(
        'count' => $count,
      ));

    return $graph;
  }

}
