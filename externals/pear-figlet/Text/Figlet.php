<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4: */
/**
* ASCII art text creation
*
* Project home page (Russian): http://bolknote.ru/files/figlet/
*
* PHP Version 4
*
* @category Text
* @package  Text_Figlet
* @author   Evgeny Stepanischev <imbolk@gmail.com>
* @author   Christian Weiske <cweiske@php.net>
* @license  http://www.php.net/license PHP License
* @version  CVS: $Id$
* @link     http://pear.php.net/package/Text_Figlet
*/

/**
* ASCII art text creation
*
* Project home page (Russian): http://bolknote.ru/files/figlet/
*
* PHP Version 4
*
* @category Text
* @package  Text_Figlet
* @author   Evgeny Stepanischev <imbolk@gmail.com>
* @author   Christian Weiske <cweiske@php.net>
* @license  http://www.php.net/license PHP License
* @link     http://pear.php.net/package/Text_Figlet
*/
class Text_Figlet
{
    /**
     * Height of a letter
     *
     * @var integer
     *
     * @access protected
     */
    var $height;

    /**
     * Letter baseline
     *
     * @var integer
     *
     * @access protected
     */
    var $oldlayout;

    /**
     * Flag - RTL (right to left) or LTR (left to right) text direction
     *
     * @var integer
     *
     * @access protected
     */
    var $rtol;

    /**
     * Information about special 'hardblank' character
     *
     * @var integer
     *
     * @access protected
     */
    var $hardblank;

    /**
     * Is used for keeping font
     *
     * @var array
     *
     * @access protected
     */
    var $font;

    /**
     * Flag is true if smushing occured in letters printing cycle
     *
     * @var integer
     *
     * @access protected
     */
    var $smush_flag;

    /**
     * Comment lines buffer
     *
     * @var string
     *
     * @access public
     */

    var $font_comment;


    /**
     * Load user font. Must be invoked first.
     * Automatically tries the Text_Figlet font data directory
     *  as long as no path separator is in the filename.
     *
     * @param string $filename   font file name
     * @param bool   $loadgerman (optional) load German character set or not
     *
     * @access public
     * @return mixed PEAR_Error or true for success
     */
    function loadFont($filename, $loadgerman = true)
    {
        $this->font = array();
        if (!file_exists($filename)) {
          return self::raiseError('Figlet font file "'
                                  . $filename
                                  . '" cannot be found', 1);
        }

        $this->font_comment = '';

        // If Gzip compressed font
        if (substr($filename, -3, 3) == '.gz') {
            $filename   = 'compress.zlib://' . $filename;
            $compressed = true;

            if (!function_exists('gzcompress')) {
                return self::raiseError('Cannot load gzip compressed fonts since'
                                        . ' gzcompress() is not available.',
                                        3);
            }
        } else {
            $compressed = false;
        }

        if (!($fp = fopen($filename, 'rb'))) {
            return self::raiseError('Cannot open figlet font file ' . $filename, 2);
        }

        if (!$compressed) {
            /* ZIPed font */
            if (fread($fp, 2) == 'PK') {
                if (!function_exists('zip_open')) {
                    return self::raiseError('Cannot load ZIP compressed fonts since'
                                            . ' ZIP PHP extension is not available.',
                                            5);
                }

                fclose($fp);

                if (!($fp = zip_open($filename))) {
                    return self::raiseError('Cannot open figlet font file ' . $filename, 2);
                }

                $name = zip_entry_name(zip_read($fp));
                zip_close($fp);

                if (!($fp = fopen('zip://' . realpath($filename) . '#' . $name, 'rb'))) {
                    return self::raiseError('Cannot open figlet font file ' . $filename, 2);
                }

                $compressed = true;
            } else {
                flock($fp, LOCK_SH);
                rewind($fp);
            }
        }

        //            flf2a$ 6 5 20 15 3 0 143 229
        //              |  | | | |  |  | |  |   |
        //             /  /  | | |  |  | |  |   \
        //    Signature  /  /  | |  |  | |   \   Codetag_Count
        //      Hardblank  /  /  |  |  |  \   Full_Layout
        //           Height  /   |  |   \  Print_Direction
        //           Baseline   /    \   Comment_Lines
        //            Max_Length      Old_Layout


        $header = explode(' ', fgets($fp, 2048));

        if (substr($header[0], 0, 5) <> 'flf2a') {
            return self::raiseError('Unknown FIGlet font format.', 4);
        }

        @list ($this->hardblank, $this->height,,,
        $this->oldlayout, $cmt_count, $this->rtol) = $header;

        $this->hardblank = substr($this->hardblank, -1, 1);

        for ($i = 0; $i < $cmt_count; $i++) {
            $this->font_comment .= fgets($fp, 2048);
        }

        // ASCII charcters
        for ($i = 32; $i < 127; $i++) {
            $this->font[$i] = $this->_char($fp);
        }

        foreach (array(196, 214, 220, 228, 246, 252, 223) as $i) {
            if ($loadgerman) {
                $letter = $this->_char($fp);

                // Invalid character but main font is loaded and I can use it
                if ($letter === false) {
                    fclose($fp);
                    return true;
                }

                // Load if it is not blank only
                if (trim(implode('', $letter)) <> '') {
                    $this->font[$i] = $letter;
                }
            } else {
                $this->_skip($fp);
            }
        }

        // Extented characters
        for ($n = 0; !feof($fp); $n++) {
            list ($i) = explode(' ', rtrim(fgets($fp, 1024)), 2);
            if ($i == '') {
                continue;
            }

            // If comment
            if (preg_match('/^\-0x/i', $i)) {
                $this->_skip($fp);
            } else {
                // If Unicode
                if (preg_match('/^0x/i', $i)) {
                    $i = hexdec(substr($i, 2));
                } else {
                    // If octal
                    if ($i{0} === '0' && $i !== '0' || substr($i, 0, 2) == '-0') {
                        $i = octdec($i);
                    }
                }

                $letter = $this->_char($fp);

                // Invalid character but main font is loaded and I can use it
                if ($letter === false) {
                    fclose($fp);
                    return true;
                }

                $this->font[$i] = $letter;
            }
        }

        fclose($fp);
        return true;
    }



    /**
    * Print string using font loaded by LoadFont method
    *
    * @param string $str    string for printing
    * @param bool   $inhtml (optional) output mode
    *                       - HTML (true) or plain text (false)
    *
    * @access public
    * @return string contains
    */
    function lineEcho($str, $inhtml = false)
    {
        $out = array();

        for ($i = 0; $i<strlen($str); $i++) {
            // Pseudo Unicode support
            if (substr($str, $i, 2) == '%u') {
                $lt = hexdec(substr($str, $i+2, 4));
                $i += 5;
            } else {
                $lt = ord($str{$i});
            }

            $hb = preg_quote($this->hardblank, '/');
            $sp = "$hb\\x00\\s";

            // If chosen character not found try to use default
            // If default character is not defined skip it

            if (!isset($this->font[$lt])) {
                if (isset($this->font[0])) {
                    $lt = 0;
                } else {
                    continue;
                }
            }

            for ($j = 0; $j < $this->height; $j++) {
                $line = $this->font[$lt][$j];

                // Replace hardblanks
                if (isset($out[$j])) {
                    if ($this->rtol) {
                        $out[$j] = $line . $out[$j];
                    } else {
                        $out[$j] .= $line;
                    }
                } else {
                    $out[$j] = $line;
                }
            }

            if ($this->oldlayout > -1 && $i) {
                // Calculate minimal distance between two last letters

                $mindiff = -1;

                for ($j = 0; $j < $this->height; $j++) {
                    if (preg_match("/\S(\s*\\x00\s*)\S/", $out[$j], $r)) {
                        if ($mindiff == -1) {
                            $mindiff = strlen($r[1]);
                        } else {
                            $mindiff = min($mindiff, strlen($r[1]));
                        }
                    }
                }

                // Remove spaces between two last letter
                // dec mindiff for exclude \x00 symbol

                if (--$mindiff > 0) {
                    for ($j = 0; $j < $this->height; $j++) {
                        if (preg_match("/\\x00(\s{0,{$mindiff}})/", $out[$j], $r)) {
                            $l       = strlen($r[1]);
                            $b       = $mindiff - $l;
                            $out[$j] = preg_replace("/\s{0,$b}\\x00\s{{$l}}/",
                                                    "\0",
                                                    $out[$j],
                                                    1);
                        }
                    }
                }
                // Smushing

                $this->smush_flag = 0;

                for ($j = 0; $j < $this->height; $j++) {
                    $out[$j] = preg_replace_callback("#([^$sp])\\x00([^$sp])#",
                                                     array(&$this, '_rep'),
                                                     $out[$j]);
                }

                // Remove one space if smushing
                // and remove all \x00 except tail whenever

                if ($this->smush_flag) {
                    $pat = array("/\s\\x00(?!$)|\\x00\s/", "/\\x00(?!$)/");
                    $rep = array('', '');
                } else {
                    $pat = "/\\x00(?!$)/";
                    $rep = '';
                }

                for ($j = 0; $j<$this->height; $j++) {
                    $out[$j] = preg_replace($pat, $rep, $out[$j]);
                }
            }
        }

        $trans = array("\0" => '', $this->hardblank => ' ');
        $str   = strtr(implode("\n", $out), $trans);

        if ($inhtml) {
          self::raiseError(
            'Do not use the HTML escaping provided by this class in '.
            'a Phabricator context.');
        }

        return $str;
    }



    /**
    * It is preg_replace callback function that makes horizontal letter smushing
    *
    * @param array $r preg_replace matches array
    *
    * @return string
    * @access private
    */
    function _rep($r)
    {
        if ($this->oldlayout & 1 && $r[1] == $r[2]) {
            $this->smush_flag = 1;
            return $r[1];
        }

        if ($this->oldlayout & 2) {
            $symb = '|/\\[]{}()<>';

            if ($r[1] == '_' && strpos($symb, $r[2]) !== false ||
                $r[2] == '_' && strpos($symb, $r[1]) !== false) {
                $this->smush_flag = 1;
                return $r[1];
            }
        }

        if ($this->oldlayout & 4) {
            $classes = '|/\\[]{}()<>';

            if (($left = strpos($classes, $r[1])) !== false) {
                if (($right = strpos($classes, $r[2])) !== false) {
                    $this->smush_flag = 1;
                    return $right > $left ? $r[2] : $r[1];
                }
            }
        }

        if ($this->oldlayout & 8) {
            $t = array('[' => ']', ']' => '[', '{' => '}', '}' => '{',
            '(' => ')', ')' => '(');

            if (isset($t[$r[2]]) && $r[1] == $t[$r[2]]) {
                $this->smush_flag = 1;
                return '|';
            }
        }

        if ($this->oldlayout & 16) {
            $t = array("/\\" => '|', "\\/" => 'Y', '><' => 'X');

            if (isset($t[$r[1].$r[2]])) {
                $this->smush_flag = 1;
                return $t[$r[1].$r[2]];
            }
        }

        if ($this->oldlayout & 32) {
            if ($r[1] == $r[2] && $r[1] == $this->hardblank) {
                $this->smush_flag = 1;
                return $this->hardblank;
            }
        }

        return $r[1]."\00".$r[2];
    }



    /**
    * Function loads one character in the internal array from file
    *
    * @param resource &$fp handle of font file
    *
    * @return mixed lines of the character or false if foef occured
    * @access private
    */
    function _char(&$fp)
    {
        $out = array();

        for ($i = 0; $i < $this->height; $i++) {
            if (feof($fp)) {
                return false;
            }

            $line = rtrim(fgets($fp, 2048), "\r\n");
            if (preg_match('/(.){1,2}$/', $line, $r)) {
                $line = str_replace($r[1], '', $line);
            }

            $line .= "\x00";

            $out[] = $line;
        }

        return $out;
    }



    /**
    * Function for skipping one character in a font file
    *
    * @param resource &$fp handle of font file
    *
    * @return boolean always return true
    * @access private
    */
    function _skip(&$fp)
    {
        for ($i = 0; $i<$this->height && !feof($fp); $i++) {
            fgets($fp, 2048);
        }

        return true;
    }


  private static function raiseError($message, $code = 1) {
    throw new Exception($message);
  }
}
