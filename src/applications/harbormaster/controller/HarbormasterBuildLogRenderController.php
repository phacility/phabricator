<?php

final class HarbormasterBuildLogRenderController
  extends HarbormasterController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $id = $request->getURIData('id');

    $log = id(new HarbormasterBuildLogQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$log) {
      return new Aphront404Response();
    }

    $highlight_range = $request->getURILineRange('lines', 1000);

    $log_size = $this->getTotalByteLength($log);

    $head_lines = $request->getInt('head');
    if ($head_lines === null) {
      $head_lines = 8;
    }
    $head_lines = min($head_lines, 1024);
    $head_lines = max($head_lines, 0);

    $tail_lines = $request->getInt('tail');
    if ($tail_lines === null) {
      $tail_lines = 16;
    }
    $tail_lines = min($tail_lines, 1024);
    $tail_lines = max($tail_lines, 0);

    $head_offset = $request->getInt('headOffset');
    if ($head_offset === null) {
      $head_offset = 0;
    }

    $tail_offset = $request->getInt('tailOffset');
    if ($tail_offset === null) {
      $tail_offset = $log_size;
    }

    // Figure out which ranges we're actually going to read. We'll read either
    // one range (either just at the head, or just at the tail) or two ranges
    // (one at the head and one at the tail).

    // This gets a little bit tricky because: the ranges may overlap; we just
    // want to do one big read if there is only a little bit of text left
    // between the ranges; we may not know where the tail range ends; and we
    // can only read forward from line map markers, not from any arbitrary
    // position in the file.

    $bytes_per_line = 140;
    $body_lines = 8;

    $views = array();
    if ($head_lines > 0) {
      $views[] = array(
        'offset' => $head_offset,
        'lines' => $head_lines,
        'direction' => 1,
        'limit' => $tail_offset,
      );
    }

    if ($highlight_range) {
      $highlight_views = $this->getHighlightViews(
        $log,
        $highlight_range,
        $log_size);
      foreach ($highlight_views as $highlight_view) {
        $views[] = $highlight_view;
      }
    }

    if ($tail_lines > 0) {
      $views[] = array(
        'offset' => $tail_offset,
        'lines' => $tail_lines,
        'direction' => -1,
        'limit' => $head_offset,
      );
    }

    $reads = $views;
    foreach ($reads as $key => $read) {
      $offset = $read['offset'];

      $lines = $read['lines'];

      $read_length = 0;
      $read_length += ($lines * $bytes_per_line);
      $read_length += ($body_lines * $bytes_per_line);

      $direction = $read['direction'];
      if ($direction < 0) {
        if ($offset > $read_length) {
          $offset -= $read_length;
        } else {
          $read_length = $offset;
          $offset = 0;
        }
      }

      $position = $log->getReadPosition($offset);
      list($position_offset, $position_line) = $position;
      $read_length += ($offset - $position_offset);

      $reads[$key]['fetchOffset'] = $position_offset;
      $reads[$key]['fetchLength'] = $read_length;
      $reads[$key]['fetchLine'] = $position_line;
    }

    $reads = $this->mergeOverlappingReads($reads);

    foreach ($reads as $key => $read) {
      $fetch_offset = $read['fetchOffset'];
      $fetch_length = $read['fetchLength'];
      if ($fetch_offset + $fetch_length > $log_size) {
        $fetch_length = $log_size - $fetch_offset;
      }

      $data = $log->loadData($fetch_offset, $fetch_length);

      $offset = $read['fetchOffset'];
      $line = $read['fetchLine'];
      $lines = $this->getLines($data);
      $line_data = array();
      foreach ($lines as $line_text) {
        $length = strlen($line_text);
        $line_data[] = array(
          'offset' => $offset,
          'length' => $length,
          'line' => $line,
          'data' => $line_text,
        );
        $line += 1;
        $offset += $length;
      }

      $reads[$key]['data'] = $data;
      $reads[$key]['lines'] = $line_data;
    }

    foreach ($views as $view_key => $view) {
      $anchor_byte = $view['offset'];

      if ($view['direction'] < 0) {
        $anchor_byte = $anchor_byte - 1;
      }

      $data_key = null;
      foreach ($reads as $read_key => $read) {
        $s = $read['fetchOffset'];
        $e = $s + $read['fetchLength'];

        if (($s <= $anchor_byte) && ($e >= $anchor_byte)) {
          $data_key = $read_key;
          break;
        }
      }

      if ($data_key === null) {
        throw new Exception(
          pht('Unable to find fetch!'));
      }

      $anchor_key = null;
      foreach ($reads[$data_key]['lines'] as $line_key => $line) {
        $s = $line['offset'];
        $e = $s + $line['length'];

        if (($s <= $anchor_byte) && ($e > $anchor_byte)) {
          $anchor_key = $line_key;
          break;
        }
      }

      if ($anchor_key === null) {
        throw new Exception(
          pht(
            'Unable to find lines.'));
      }

      if ($view['direction'] > 0) {
        $slice_offset = $anchor_key;
      } else {
        $slice_offset = max(0, $anchor_key - ($view['lines'] - 1));
      }
      $slice_length = $view['lines'];

      $views[$view_key] += array(
        'sliceKey' => $data_key,
        'sliceOffset' => $slice_offset,
        'sliceLength' => $slice_length,
      );
    }

    foreach ($views as $view_key => $view) {
      $slice_key = $view['sliceKey'];
      $lines = array_slice(
        $reads[$slice_key]['lines'],
        $view['sliceOffset'],
        $view['sliceLength']);

      $data_offset = null;
      $data_length = null;
      foreach ($lines as $line) {
        if ($data_offset === null) {
          $data_offset = $line['offset'];
        }
        $data_length += $line['length'];
      }

      // If the view cursor starts in the middle of a line, we're going to
      // strip part of the line.
      $direction = $view['direction'];
      if ($direction > 0) {
        $view_offset = $view['offset'];
        $view_length = $data_length;
        if ($data_offset < $view_offset) {
          $trim = ($view_offset - $data_offset);
          $view_length -= $trim;
        }

        $limit = $view['limit'];
        if ($limit !== null) {
          if ($limit < ($view_offset + $view_length)) {
            $view_length = ($limit - $view_offset);
          }
        }
      } else {
        $view_offset = $data_offset;
        $view_length = $data_length;
        if ($data_offset + $data_length > $view['offset']) {
          $view_length -= (($data_offset + $data_length) - $view['offset']);
        }

        $limit = $view['limit'];
        if ($limit !== null) {
          if ($limit > $view_offset) {
            $view_length -= ($limit - $view_offset);
            $view_offset = $limit;
          }
        }
      }

      $views[$view_key] += array(
        'viewOffset' => $view_offset,
        'viewLength' => $view_length,
      );
    }

    $views = $this->mergeOverlappingViews($views);

    foreach ($views as $view_key => $view) {
      $slice_key = $view['sliceKey'];
      $lines = array_slice(
        $reads[$slice_key]['lines'],
        $view['sliceOffset'],
        $view['sliceLength']);

      $view_offset = $view['viewOffset'];
      foreach ($lines as $line_key => $line) {
        $line_offset = $line['offset'];

        if ($line_offset >= $view_offset) {
          break;
        }

        $trim = ($view_offset - $line_offset);
        if ($trim && ($trim >= strlen($line['data']))) {
          unset($lines[$line_key]);
          continue;
        }

        $line_data = substr($line['data'], $trim);
        $lines[$line_key]['data'] = $line_data;
        $lines[$line_key]['length'] = strlen($line_data);
        $lines[$line_key]['offset'] += $trim;
        break;
      }

      $view_end = $view['viewOffset'] + $view['viewLength'];
      foreach ($lines as $line_key => $line) {
        $line_end = $line['offset'] + $line['length'];
        if ($line_end <= $view_end) {
          continue;
        }

        $trim = ($line_end - $view_end);
        if ($trim && ($trim >= strlen($line['data']))) {
          unset($lines[$line_key]);
          continue;
        }

        $line_data = substr($line['data'], -$trim);
        $lines[$line_key]['data'] = $line_data;
        $lines[$line_key]['length'] = strlen($line_data);
      }

      $views[$view_key]['viewData'] = $lines;
    }

    $spacer = null;
    $render = array();

    $head_view = head($views);
    if ($head_view['viewOffset'] > $head_offset) {
      $render[] = array(
        'spacer' => true,
        'head' => $head_offset,
        'tail' => $head_view['viewOffset'],
      );
    }

    foreach ($views as $view) {
      if ($spacer) {
        $spacer['tail'] = $view['viewOffset'];
        $render[] = $spacer;
      }

      $render[] = $view;

      $spacer = array(
        'spacer' => true,
        'head' => ($view['viewOffset'] + $view['viewLength']),
      );
    }

    $tail_view = last($views);
    if ($tail_view['viewOffset'] + $tail_view['viewLength'] < $tail_offset) {
      $render[] = array(
        'spacer' => true,
        'head' => $tail_view['viewOffset'] + $tail_view['viewLength'],
        'tail' => $tail_offset,
      );
    }

    $uri = $log->getURI();

    $rows = array();
    foreach ($render as $range) {
      if (isset($range['spacer'])) {
        $rows[] = $this->renderExpandRow($range);
        continue;
      }

      $lines = $range['viewData'];
      foreach ($lines as $line) {
        $display_line = ($line['line'] + 1);
        $display_text = ($line['data']);

        $row_attr = array();
        if ($highlight_range) {
          if (($display_line >= $highlight_range[0]) &&
              ($display_line <= $highlight_range[1])) {
            $row_attr = array(
              'class' => 'phabricator-source-highlight',
            );
          }
        }

        $display_line = phutil_tag(
          'a',
          array(
            'href' => $uri.'$'.$display_line,
            'data-n' => $display_line,
          ),
          '');

        $line_cell = phutil_tag('th', array(), $display_line);
        $text_cell = phutil_tag('td', array(), $display_text);

        $rows[] = phutil_tag(
          'tr',
          $row_attr,
          array(
            $line_cell,
            $text_cell,
          ));
      }
    }

    if ($log->getLive()) {
      $last_view = last($views);
      $last_line = last($last_view['viewData']);
      if ($last_line) {
        $last_offset = $last_line['offset'];
      } else {
        $last_offset = 0;
      }

      $last_tail = $last_view['viewOffset'] + $last_view['viewLength'];
      $show_live = ($last_tail === $log_size);
      if ($show_live) {
        $rows[] = $this->renderLiveRow($last_offset);
      }
    }

    $table = javelin_tag(
      'table',
      array(
        'class' => 'harbormaster-log-table PhabricatorMonospaced',
        'sigil' => 'phabricator-source',
        'meta' => array(
          'uri' => $log->getURI(),
        ),
      ),
      $rows);

    // When this is a normal AJAX request, return the rendered log fragment
    // in an AJAX payload.
    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())
        ->setContent(
          array(
            'markup' => hsprintf('%s', $table),
          ));
    }

    // If the page is being accessed as a standalone page, present a
    // readable version of the fragment for debugging.

    require_celerity_resource('harbormaster-css');

    $header = pht('Standalone Log Fragment');

    $render_view = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setHeaderText($header)
      ->appendChild($table);

    $page_view = id(new PHUITwoColumnView())
      ->setFooter($render_view);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Build Log %d', $log->getID()), $log->getURI())
      ->addTextCrumb(pht('Fragment'))
      ->setBorder(true);

    return $this->newPage()
      ->setTitle(
        array(
          pht('Build Log %d', $log->getID()),
          pht('Standalone Fragment'),
        ))
      ->setCrumbs($crumbs)
      ->appendChild($page_view);
  }

  private function getTotalByteLength(HarbormasterBuildLog $log) {
    $total_bytes = $log->getByteLength();
    if ($total_bytes) {
      return (int)$total_bytes;
    }

    // TODO: Remove this after enough time has passed for installs to run
    // log rebuilds or decide they don't care about older logs.

    // Older logs don't have this data denormalized onto the log record unless
    // an administrator has run `bin/harbormaster rebuild-log --all` or
    // similar. Try to figure it out by summing up the size of each chunk.

    // Note that the log may also be legitimately empty and have actual size
    // zero.
    $chunk = new HarbormasterBuildLogChunk();
    $conn = $chunk->establishConnection('r');

    $row = queryfx_one(
      $conn,
      'SELECT SUM(size) total FROM %T WHERE logID = %d',
      $chunk->getTableName(),
      $log->getID());

    return (int)$row['total'];
  }

  private function getLines($data) {
    $parts = preg_split("/(\r\n|\r|\n)/", $data, 0, PREG_SPLIT_DELIM_CAPTURE);

    if (last($parts) === '') {
      array_pop($parts);
    }

    $lines = array();
    for ($ii = 0; $ii < count($parts); $ii += 2) {
      $line = $parts[$ii];
      if (isset($parts[$ii + 1])) {
        $line .= $parts[$ii + 1];
      }
      $lines[] = $line;
    }

    return $lines;
  }


  private function mergeOverlappingReads(array $reads) {
    // Find planned reads which will overlap and merge them into a single
    // larger read.

    $uk = array_keys($reads);
    $vk = array_keys($reads);

    foreach ($uk as $ukey) {
      foreach ($vk as $vkey) {
        // Don't merge a range into itself, even though they do technically
        // overlap.
        if ($ukey === $vkey) {
          continue;
        }

        $uread = idx($reads, $ukey);
        if ($uread === null) {
          continue;
        }

        $vread = idx($reads, $vkey);
        if ($vread === null) {
          continue;
        }

        $us = $uread['fetchOffset'];
        $ue = $us + $uread['fetchLength'];

        $vs = $vread['fetchOffset'];
        $ve = $vs + $vread['fetchLength'];

        if (($vs > $ue) || ($ve < $us)) {
          continue;
        }

        $min = min($us, $vs);
        $max = max($ue, $ve);

        $reads[$ukey]['fetchOffset'] = $min;
        $reads[$ukey]['fetchLength'] = ($max - $min);
        $reads[$ukey]['fetchLine'] = min(
          $uread['fetchLine'],
          $vread['fetchLine']);

        unset($reads[$vkey]);
      }
    }

    return $reads;
  }

  private function mergeOverlappingViews(array $views) {
    $uk = array_keys($views);
    $vk = array_keys($views);

    $body_lines = 8;
    $body_bytes = ($body_lines * 140);

    foreach ($uk as $ukey) {
      foreach ($vk as $vkey) {
        if ($ukey === $vkey) {
          continue;
        }

        $uview = idx($views, $ukey);
        if ($uview === null) {
          continue;
        }

        $vview = idx($views, $vkey);
        if ($vview === null) {
          continue;
        }

        // If these views don't use the same line data, don't try to
        // merge them.
        if ($uview['sliceKey'] != $vview['sliceKey']) {
          continue;
        }

        // If these views are overlapping or separated by only a few bytes,
        // merge them into a single view.
        $us = $uview['viewOffset'];
        $ue = $us + $uview['viewLength'];

        $vs = $vview['viewOffset'];
        $ve = $vs + $vview['viewLength'];

        // Don't merge if one of the slices starts at a byte offset
        // significantly after the other ends.
        if (($vs > $ue + $body_bytes) || ($us > $ve + $body_bytes)) {
          continue;
        }

        $uss = $uview['sliceOffset'];
        $use = $uss + $uview['sliceLength'];

        $vss = $vview['sliceOffset'];
        $vse = $vss + $vview['sliceLength'];

        // Don't merge if one of the slices starts at a line offset
        // significantly after the other ends.
        if ($uss > ($vse + $body_lines) || $vss > ($use + $body_lines)) {
          continue;
        }

        // These views are overlapping or nearly overlapping, so we merge
        // them. We merge views even if they aren't exactly adjacent since
        // it's silly to render an "expand more" which only expands a couple
        // of lines.

        $offset = min($us, $vs);
        $length = max($ue, $ve) - $offset;

        $slice_offset = min($uss, $vss);
        $slice_length = max($use, $vse) - $slice_offset;

        $views[$ukey] = array(
          'viewOffset' => $offset,
          'viewLength' => $length,
          'sliceOffset' => $slice_offset,
          'sliceLength' => $slice_length,
        ) + $views[$ukey];

        unset($views[$vkey]);
      }
    }

    return $views;
  }

  private function renderExpandRow($range) {

    $icon_up = id(new PHUIIconView())
      ->setIcon('fa-chevron-up');

    $icon_down = id(new PHUIIconView())
      ->setIcon('fa-chevron-down');

    $up_text = array(
      pht('Show More Above'),
      ' ',
      $icon_up,
    );

    $expand_up = javelin_tag(
      'a',
      array(
        'sigil' => 'harbormaster-log-expand',
        'meta' => array(
          'headOffset' => $range['head'],
          'tailOffset' => $range['tail'],
          'head' => 128,
          'tail' => 0,
        ),
      ),
      $up_text);

    $mid_text = pht(
      'Show More (%s Bytes)',
      new PhutilNumber($range['tail'] - $range['head']));

    $expand_mid = javelin_tag(
      'a',
      array(
        'sigil' => 'harbormaster-log-expand',
        'meta' => array(
          'headOffset' => $range['head'],
          'tailOffset' => $range['tail'],
          'head' => 128,
          'tail' => 128,
        ),
      ),
      $mid_text);

    $down_text = array(
      $icon_down,
      ' ',
      pht('Show More Below'),
    );

    $expand_down = javelin_tag(
      'a',
      array(
        'sigil' => 'harbormaster-log-expand',
        'meta' => array(
          'headOffset' => $range['head'],
          'tailOffset' => $range['tail'],
          'head' => 0,
          'tail' => 128,
        ),
      ),
      $down_text);

    $expand_cells = array(
      phutil_tag(
        'td',
        array(
          'class' => 'harbormaster-log-expand-up',
        ),
        $expand_up),
      phutil_tag(
        'td',
        array(
          'class' => 'harbormaster-log-expand-mid',
        ),
        $expand_mid),
      phutil_tag(
        'td',
        array(
          'class' => 'harbormaster-log-expand-down',
        ),
        $expand_down),
    );

    return $this->renderActionTable($expand_cells);
  }

  private function renderLiveRow($log_size) {
    $icon_down = id(new PHUIIconView())
      ->setIcon('fa-angle-double-down');

    $icon_pause = id(new PHUIIconView())
      ->setIcon('fa-pause');

    $follow = javelin_tag(
      'a',
      array(
        'sigil' => 'harbormaster-log-expand harbormaster-log-live',
        'class' => 'harbormaster-log-follow-start',
        'meta' => array(
          'headOffset' => $log_size,
          'head' => 0,
          'tail' => 1024,
          'live' => true,
        ),
      ),
      array(
        $icon_down,
        ' ',
        pht('Follow Log'),
      ));

    $stop_following = javelin_tag(
      'a',
      array(
        'sigil' => 'harbormaster-log-expand',
        'class' => 'harbormaster-log-follow-stop',
        'meta' => array(
          'stop' => true,
        ),
      ),
      array(
        $icon_pause,
        ' ',
        pht('Stop Following Log'),
      ));

    $expand_cells = array(
      phutil_tag(
        'td',
        array(
          'class' => 'harbormaster-log-follow',
        ),
        array(
          $follow,
          $stop_following,
        )),
    );

    return $this->renderActionTable($expand_cells);
  }

  private function renderActionTable(array $action_cells) {
    $action_row = phutil_tag('tr', array(), $action_cells);

    $action_table = phutil_tag(
      'table',
      array(
        'class' => 'harbormaster-log-expand-table',
      ),
      $action_row);

    $format_cells = array(
      phutil_tag('th', array()),
      phutil_tag(
        'td',
        array(
          'class' => 'harbormaster-log-expand-cell',
        ),
        $action_table),
    );

    return phutil_tag('tr', array(), $format_cells);
  }

  private function getHighlightViews(
    HarbormasterBuildLog $log,
    array $range,
    $log_size) {
    // If we're highlighting a line range in the file, we first need to figure
    // out the offsets for the lines we care about.
    list($range_min, $range_max) = $range;

    // Read the markers to find a range we can load which includes both lines.
    $read_range = $log->getLineSpanningRange($range_min, $range_max);
    list($min_pos, $max_pos, $min_line) = $read_range;

    $length = ($max_pos - $min_pos);

    // Reject to do the read if it requires us to examine a huge amount of
    // data. For example, the user may request lines "$1-1000" of a file where
    // each line has 100MB of text.
    $limit = (1024 * 1024 * 16);
    if ($length > $limit) {
      return array();
    }

    $data = $log->loadData($min_pos, $length);

    $offset = $min_pos;
    $min_offset = null;
    $max_offset = null;

    $lines = $this->getLines($data);
    $number = ($min_line + 1);

    foreach ($lines as $line) {
      if ($min_offset === null) {
        if ($number === $range_min) {
          $min_offset = $offset;
        }
      }

      $offset += strlen($line);

      if ($max_offset === null) {
        if ($number === $range_max) {
          $max_offset = $offset;
          break;
        }
      }

      $number += 1;
    }

    $context_lines = 8;

    // Build views around the beginning and ends of the respective lines. We
    // expect these views to overlap significantly in normal circumstances
    // and be merged later.
    $views = array();

    if ($min_offset !== null) {
      $views[] = array(
        'offset' => $min_offset,
        'lines' => $context_lines + ($range_max - $range_min) - 1,
        'direction' => 1,
        'limit' => null,
      );
      if ($min_offset > 0) {
        $views[] = array(
          'offset' => $min_offset,
          'lines' => $context_lines,
          'direction' => -1,
          'limit' => null,
        );
      }
    }

    if ($max_offset !== null) {
      $views[] = array(
        'offset' => $max_offset,
        'lines' => $context_lines + ($range_max - $range_min),
        'direction' => -1,
        'limit' => null,
      );
      if ($max_offset < $log_size) {
        $views[] = array(
          'offset' => $max_offset,
          'lines' => $context_lines,
          'direction' => 1,
          'limit' => null,
        );
      }
    }

    return $views;
  }

}
