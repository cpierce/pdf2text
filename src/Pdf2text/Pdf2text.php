<?php
/**
 * Library for reading text from PDF file.
 *
 * @author  Chris Pierce <cpierce@csdurant.com>
 * @license GNU General Public License Version 3 or Later
 * @package Pdf2text
 *
 * @link http://www.github.com/cpierce/pdf2text
 */

namespace Pdf2text;

/**
 * Pdf2text class.
 */
class Pdf2text
{

    /**
     * Multibyte.
     *
     * @var int
     */
    private $multibyte = 4;

    /**
     * Convert Quotes.
     *
     * @var int END_COMPAT(double-quotes),ENT_QUOTES(both),END_NOQUOTES(none)
     */
    private $convertQuotes = ENT_QUOTES;

    /**
     * Filename.
     *
     * @var string
     */
    private $filename = '';

    /**
     * Decode Text.
     *
     * @var string
     */
    private $decodedText = '';

    /**
     * Construct Method.
     *
     * @param string $filename
     * @param array  $options
     * @throws Exception
     */
    public function __construct($filename = null, $options = null)
    {
        if (!$filename) {
            throw new \RuntimeException('No Filename Specified');
        }

        $this->setFilename($filename);

        if (!empty($options)) {
            $this->setOptions($options);
        }
    }

    public function decode()
    {
        $this->decodePDF();
        return $this->output();
    }

    /**
     * Set Filename Method.
     *
     * @param string $filename
     */
    protected function setFilename($filename)
    {
        $this->filename = $filename;
    }

    /**
     * Set Options Method.
     *
     * @param array $options
     */
    protected function setOptions($options = null)
    {
        if (isset($options['convertQuotes']) &&
            !empty($options['convertQuotes'])) {
                $this->convertQuotes = $options['convertQuotes'];
        }
        if (isset($options['multibyteUnicode']) &&
            !empty($options['multibyteUnicode'])) {
                $this->multibyte = $options['multibyteUnicode'] ? 4 : 2;
        }
    }

    /**
     * Output Method.
     *
     * @return string
     */
    private function output()
    {
        return $this->decodedText;
    }

    /**
     * Decode PDF Method.
     *
     * @return string
     */
    private function decodePDF()
    {
        $infile = @file_get_contents($this->filename, FILE_BINARY);

        if (empty($infile)) {
            return '';
        }

        $transformations = [];
        $texts           = [];

        // Get the list of all objects.
        preg_match_all("#obj[\n|\r](.*)endobj[\n|\r]#ismU", $infile .
            'endobj' . "\r", $objects);
        $objects = @$objects[1];

        for ($i = 0; $i < count($objects); $i++) {
            $currentObject = $objects[$i];

            @set_time_limit();

            if (preg_match("#stream[\n|\r](.*)endstream[\n|\r]#ismU",
                $currentObject . "endstream\r", $stream )) {

                $stream = ltrim($stream[1]);
                $options = $this->getObjectOptions($currentObject);

                if (!(empty($options['Length1']) &&
                    empty($options['Type']) &&
                    empty($options['Subtype']))) {
                        continue;
                }

                unset($options['Length']);

                $data = $this->getDecodedStream($stream, $options);

                if (strlen($data)) {
                    if (preg_match_all("#BT[\n|\r](.*)ET[\n|\r]#ismU", $data .
                        'ET' . "\r", $textContainers)) {
                        $textContainers = @$textContainers[1];
                        $this->getDirtyTexts($texts, $textContainers);
                    } else {
                        $this->getCharTransformations($transformations, $data);
                    }
                }
            }
        }

        $this->decodedText = $this->getTextUsingTransformations($texts, $transformations);
    }

    /**
     * Decode ASCII Hex Method.
     *
     * @param  string $input
     * @return string
     */
    private function decodeAsciiHex($input)
    {
        $output    = '';
        $isOdd     = true;
        $isComment = false;
        $codeHigh  = -1;

        for ($i = 0; $i < strlen($input) && $input[$i] !== '>'; $i++) {
            $c = $input[$i];

            if ($isComment) {
                if ($c == '\r' || $c == '\n') {
                    $isComment = false;
                }
            }

            switch ($c) {
                case '\0':
                case '\t':
                case '\r':
                case '\f':
                case '\n':
                case ' ':
                    break;
                case '%':
                    $isComment = true;
                    break;
                default:
                    $code = hexdec($c);

                    if($code === 0 && $c != '0') {
                        return '';
                    }

                    if ($isOdd) {
                        $codeHigh = $code;
                    } else {
                        $output .= chr($codeHigh * 16 + $code);
                    }

                    $isOdd = !$isOdd;
                    break;
            }
        }

        if ($input[$i] !== '>') {
            return '';
        }

        if ($isOdd) {
            $output .= chr($codeHigh * 16);
        }

        return $output;
    }

    /**
     * Decode ASCII 85 Method.
     *
     * @param  string $input
     * @return string
     */
    private function decodeAscii85($input)
    {
        $output    = '';
        $isComment = false;
        $ords      = [];
        $state     = 0;

        for ($i = 0; $i < strlen($input) && $input[$i] !== '~'; $i++) {
            $c = $input[$i];

            if ($isComment) {
                if ($c === '\r' || $c === '\n') {
                    $isComment = false;
                }
                continue;
            }

            if ($c === '\0' ||
                $c === '\t' ||
                $c === '\r' ||
                $c === '\f' ||
                $c === '\n' ||
                $c === ' ') {
                    continue;
            }

            if ($c === '%') {
                $isComment = true;
                continue;
            }

            if ($c === 'z' && $state === 0) {
                $output .= str_repeat(chr(0), 4);
                continue;
            }

            if ($c < '!' || $c > 'u') {
                return '';
            }

            $code           = ord($input[$i]) & 0xff;
            $ords[$state++] = $code - ord('!');

            if ($state === 5) {
                $state = 0;

                for ($sum = 0, $j = 0; $j < 5; $j++) {
                    $sum = $sum * 85 + $ords[$j];
                }
                for ($j = 3; $j >= 0; $j--) {
                    $output .= chr($sum >> ($j * 8));
                }
            }
        }

        if ($state === 1) {
            return '';
        } elseif ($state > 1) {
            for ($i = 0, $sum = 0; $i < $state; $i++) {
                $sum += ($ords[$i] + ($i == $state - 1)) * pow(85, 4 - $i);
            }
            for ($i = 0; $i < $state - 1; $i++) {
                try {
                    if (!($o = chr($sum >> ((3 - $i) * 8)))) {
                        throw new \RuntimeException('An error occurred.');
                    }
                    $output .= $o;
                } catch (Exception $e) {
                    // Don't do anything
                }
            }
        }

        return $output;
    }

    /**
     * Decode Flate Method
     *
     * @param  string $data
     * @return string
     */
    private function decodeFlate($data)
    {
        return @gzuncompress($data);
    }

    /**
     * Get Object Options Method.
     *
     * @param  object $object
     * @return array
     */
    private function getObjectOptions($object)
    {
        $options = [];

        if (preg_match("#<<(.*)>>#ismU", $object, $options)) {
            $options = explode('/', $options[1]);
            @array_shift($options);

            $o = [];
            for ($j = 0; $j < @count($options); $j++) {
                $options[$j] = preg_replace("#\s+#", ' ', trim($options[$j]));
                if (strpos($options[$j], ' ') !== false) {
                    $parts = explode(' ', $options[$j]);
                    $o[$parts[0]] = $parts[1];
                } else
                    $o[$options[$j]] = true;
            }
            $options = $o;
            unset($o);
        }

        return $options;
    }

    /**
     * Get Decoded Stream
     *
     * @param  string $stream
     * @param  array $options
     * @return string
     */
    private function getDecodedStream($stream, $options)
    {
        $data = '';

        if (empty($options['Filter'])) {
            $data = $stream;
        } else {
            $length  = !empty($options['Length']) ?
                $options['Length'] : strlen($stream);
            $_stream = substr($stream, 0, $length);

            foreach ($options as $key => $value) {
                switch ($key) {
                    case 'ASCIIHexDecode':
                        $_stream = $this->decodeAsciiHex($_stream);
                        break;
                    case 'ASCII85Decode':
                        $_stream = $this->decodeAscii85($_stream);
                        break;
                    case 'FlateDecode':
                        $_stream = $this->decodeFlate($_stream);
                        break;
                    default:
                        break;
                }
            }
            $data = $_stream;
        }

        return $data;
    }

    /**
     * Get Dirty Texts Method.
     *
     * @param  array $texts by reference
     * @param  array $textContainers
     */
    private function getDirtyTexts(&$texts, $textContainers)
    {
        for ($j = 0; $j < count($textContainers); $j++) {
            if (preg_match_all("#\[(.*)\]\s*TJ[\n|\r]#ismU",
                $textContainers[$j], $parts)) {
                    $texts = array_merge($texts, [
                        @implode('', $parts[1])
                    ]);
            } elseif (preg_match_all("#T[d|w|m|f]\s*(\(.*\))\s*Tj[\n|\r]#ismU",
                $textContainers[$j], $parts)) {
                    $texts = array_merge($texts, [
                        @implode('', $parts[1])
                    ]);
            } elseif (preg_match_all("#T[d|w|m|f]\s*(\[.*\])\s*Tj[\n|\r]#ismU",
                $textContainers[$j], $parts)) {
                    $texts = array_merge($texts, [
                        @implode('', $parts[1])
                    ]);
            }
        }

    }

    /**
     * Get Char Transformations Method.
     *
     * @param  array $transformations by reference
     * @param  string $stream
     */
    private function getCharTransformations(&$transformations, $stream)
    {
        preg_match_all("#([0-9]+)\s+beginbfchar(.*)endbfchar#ismU",
            $stream, $chars, PREG_SET_ORDER);
        preg_match_all("#([0-9]+)\s+beginbfrange(.*)endbfrange#ismU",
            $stream, $ranges, PREG_SET_ORDER);

        for ($j = 0; $j < count($chars); $j++) {
            $count = $chars[$j][1];
            $current = explode("\n", trim($chars[$j][2]));
            for ($k = 0; $k < $count && $k < count($current); $k++) {
                if (preg_match("#<([0-9a-f]{2,4})>\s+<([0-9a-f]{4,512})>#is",
                    trim($current[$k]), $map)) {
                        $transformations[str_pad($map[1], 4, "0")] = $map[2];
                }
            }
        }
        for ($j = 0; $j < count($ranges); $j++) {
            $count = $ranges[$j][1];
            $current = explode("\n", trim($ranges[$j][2]));
            for ($k = 0; $k < $count && $k < count($current); $k++) {
                if (preg_match("#<([0-9a-f]{4})>\s+<([0-9a-f]{4})>\s+<([0-9a-f]{4})>#is",
                    trim($current[$k]), $map)) {
                        $from  = hexdec($map[1]);
                        $to    = hexdec($map[2]);
                        $_from = hexdec($map[3]);

                    for ($m = $from, $n = 0; $m <= $to; $m++, $n++) {
                        $transformations[sprintf("%04X", $m)] =
                            sprintf("%04X", $_from + $n);
                    }
                } elseif (preg_match("#<([0-9a-f]{4})>\s+<([0-9a-f]{4})>\s+\[(.*)\]#ismU",
                    trim($current[$k]), $map)) {
                        $from  = hexdec($map[1]);
                        $to    = hexdec($map[2]);
                        $parts = preg_split("#\s+#", trim($map[3]));

                    for ($m = $from, $n = 0;
                        $m <= $to && $n < count($parts);
                        $m++, $n++) {
                            $transformations[sprintf("%04X", $m)] =
                                sprintf("%04X", hexdec($parts[$n]));
                    }
                }
            }
        }
    }

    /**
     * Get Text Using transformations
     *
     * @param  array $texts
     * @param  array $transformations
     * @return string
     */
    private function getTextUsingTransformations($texts, $transformations)
    {
        $document = '';

        for ($i = 0; $i < count($texts); $i++) {
            $isHex   = false;
            $isPlain = false;
            $hex     = '';
            $plain   = '';

            for ($j = 0; $j < strlen($texts[$i]); $j++) {
                $c = $texts[$i][$j];

                switch($c) {
                    case '<':
                        $hex     = '';
                        $isHex   = true;
                        $isPlain = false;
                        break;
                    case '>':
                        $hexs = str_split($hex, $this->multibyte);
                        for ($k = 0; $k < count($hexs); $k++) {

                            $chex = str_pad($hexs[$k], 4, '0');
                            if (isset($transformations[$chex])) {
                                $chex = $transformations[$chex];
                            }
                            $document .= html_entity_decode('&#x'.$chex.';');
                        }
                        $isHex = false;
                        break;
                    case '(':
                        $plain   = '';
                        $isPlain = true;
                        $isHex   = false;
                        break;
                    case ')':
                        $document .= $plain;
                        $isPlain   = false;
                        break;
                    case '\\':
                        $c2 = $texts[$i][$j + 1];

                        if (in_array($c2, ['\\', '(', ')'])) {
                            $plain .= $c2;
                        } elseif ($c2 === 'n') {
                            $plain .= '\n';
                        } elseif ($c2 === 'r') {
                            $plain .= '\r';
                        } elseif ($c2 === 't') {
                            $plain .= '\t';
                        } elseif ($c2 === 'b') {
                            $plain .= '\b';
                        } elseif ($c2 === 'f') {
                            $plain .= '\f';
                        } elseif ($c2 >= '0' && $c2 <= '9') {
                            $oct    = preg_replace("#[^0-9]#", '',
                                substr($texts[$i], $j + 1, 3));
                            $j     += strlen($oct) - 1;
                            $plain .= html_entity_decode('&#'.octdec($oct).';',
                                $this->convertQuotes);
                        }
                        $j++;
                        break;

                    default:
                        if ($isHex)
                            $hex .= $c;
                        elseif ($isPlain)
                            $plain .= $c;
                        break;
                }
            }
            $document .= "\n";
        }

        return $document;
    }
}
