<?php

final class AphrontMultipartParser extends Phobject {

  private $contentType;
  private $boundary;

  private $buffer;
  private $body;
  private $state;

  private $part;
  private $parts;

  public function setContentType($content_type) {
    $this->contentType = $content_type;
    return $this;
  }

  public function getContentType() {
    return $this->contentType;
  }

  public function beginParse() {
    $content_type = $this->getContentType();
    if ($content_type === null) {
      throw new PhutilInvalidStateException('setContentType');
    }

    if (!preg_match('(^multipart/form-data)', $content_type)) {
      throw new Exception(
        pht(
          'Expected "multipart/form-data" content type when executing a '.
          'multipart body read.'));
    }

    $type_parts = preg_split('(\s*;\s*)', $content_type);
    $boundary = null;
    foreach ($type_parts as $type_part) {
      $matches = null;
      if (preg_match('(^boundary=(.*))', $type_part, $matches)) {
        $boundary = $matches[1];
        break;
      }
    }

    if ($boundary === null) {
      throw new Exception(
        pht('Received "multipart/form-data" request with no "boundary".'));
    }

    $this->parts = array();
    $this->part = null;

    $this->buffer = '';
    $this->boundary = $boundary;

    // We're looking for a (usually empty) body before the first boundary.
    $this->state = 'bodynewline';
  }

  public function continueParse($bytes) {
    $this->buffer .= $bytes;

    $continue = true;
    while ($continue) {
      switch ($this->state) {
        case 'endboundary':
          // We've just parsed a boundary. Next, we expect either "--" (which
          // indicates we've reached the end of the parts) or "\r\n" (which
          // indicates we should read the headers for the next part).

          if (strlen($this->buffer) < 2) {
            // We don't have enough bytes yet, so wait for more.
            $continue = false;
            break;
          }

          if (!strncmp($this->buffer, '--', 2)) {
            // This is "--" after a boundary, so we're done. We'll read the
            // rest of the body (the "epilogue") and discard it.
            $this->buffer = substr($this->buffer, 2);
            $this->state = 'epilogue';

            $this->part = null;
            break;
          }

          if (!strncmp($this->buffer, "\r\n", 2)) {
            // This is "\r\n" after a boundary, so we're going to going to
            // read the headers for a part.
            $this->buffer = substr($this->buffer, 2);
            $this->state = 'header';

            // Create the object to hold the part we're about to read.
            $part = new AphrontMultipartPart();
            $this->parts[] = $part;
            $this->part = $part;
            break;
          }

          throw new Exception(
            pht('Expected "\r\n" or "--" after multipart data boundary.'));
        case 'header':
          // We've just parsed a boundary, followed by "\r\n". We are going
          // to read the headers for this part. They are in the form of HTTP
          // headers and terminated by "\r\n". The section is terminated by
          // a line with no header on it.

          if (strlen($this->buffer) < 2) {
            // We don't have enough data to find a "\r\n", so wait for more.
            $continue = false;
            break;
          }

          if (!strncmp("\r\n", $this->buffer, 2)) {
            // This line immediately began "\r\n", so we're done with parsing
            // headers. Start parsing the body.
            $this->buffer = substr($this->buffer, 2);
            $this->state = 'body';
            break;
          }

          // This is an actual header, so look for the end of it.
          $header_len = strpos($this->buffer, "\r\n");
          if ($header_len === false) {
            // We don't have a full header yet, so wait for more data.
            $continue = false;
            break;
          }

          $header_buf = substr($this->buffer, 0, $header_len);
          $this->part->appendRawHeader($header_buf);

          $this->buffer = substr($this->buffer, $header_len + 2);
          break;
        case 'body':
          // We've parsed a boundary and headers, and are parsing the data for
          // this part. The data is terminated by "\r\n--", then the boundary.

          // We'll look for "\r\n", then switch to the "bodynewline" state if
          // we find it.

          $marker = "\r";
          $marker_pos = strpos($this->buffer, $marker);

          if ($marker_pos === false) {
            // There's no "\r" anywhere in the buffer, so we can just read it
            // as provided. Then, since we read all the data, we're done until
            // we get more.

            // Note that if we're in the preamble, we won't have a "part"
            // object and will just discard the data.
            if ($this->part) {
              $this->part->appendData($this->buffer);
            }
            $this->buffer = '';
            $continue = false;
            break;
          }

          if ($marker_pos > 0) {
            // If there are bytes before the "\r",
            if ($this->part) {
              $this->part->appendData(substr($this->buffer, 0, $marker_pos));
            }
            $this->buffer = substr($this->buffer, $marker_pos);
          }

          $expect = "\r\n";
          $expect_len = strlen($expect);
          if (strlen($this->buffer) < $expect_len) {
            // We don't have enough bytes yet to know if this is "\r\n"
            // or not.
            $continue = false;
            break;
          }

          if (strncmp($this->buffer, $expect, $expect_len)) {
            // The next two bytes aren't "\r\n", so eat them and go looking
            // for more newlines.
            if ($this->part) {
              $this->part->appendData(substr($this->buffer, 0, $expect_len));
            }
            $this->buffer = substr($this->buffer, $expect_len);
            break;
          }

          // Eat the "\r\n".
          $this->buffer = substr($this->buffer, $expect_len);
          $this->state = 'bodynewline';
          break;
        case 'bodynewline':
          // We've parsed a newline in a body, or we just started parsing the
          // request. In either case, we're looking for "--", then the boundary.
          // If we find it, this section is done. If we don't, we consume the
          // bytes and move on.

          $expect = '--'.$this->boundary;
          $expect_len = strlen($expect);

          if (strlen($this->buffer) < $expect_len) {
            // We don't have enough bytes yet, so wait for more.
            $continue = false;
            break;
          }

          if (strncmp($this->buffer, $expect, $expect_len)) {
            // This wasn't the boundary, so return to the "body" state and
            // consume it. (But first, we need to append the "\r\n" which we
            // ate earlier.)
            if ($this->part) {
              $this->part->appendData("\r\n");
            }
            $this->state = 'body';
            break;
          }

          // This is the boundary, so toss it and move on.
          $this->buffer = substr($this->buffer, $expect_len);
          $this->state = 'endboundary';
          break;
        case 'epilogue':
          // We just discard any epilogue.
          $this->buffer = '';
          $continue = false;
          break;
        default:
          throw new Exception(
            pht(
              'Unknown parser state "%s".\n',
              $this->state));
      }
    }
  }

  public function endParse() {
    if ($this->state !== 'epilogue') {
      throw new Exception(
        pht(
          'Expected "multipart/form-data" parse to end '.
          'in state "epilogue".'));
    }

    return $this->parts;
  }


}
