<?php

namespace Pdf2text\Test;

use PHPUnit\Framework\TestCase;
use Pdf2text\Pdf2text;

/**
 * PDF2Text Test Class
 */
class Pdf2textTest extends TestCase
{

    /**
     * Instance property.
     *
     * @var object Pdf2text
     */
    protected $instance = null;

    /**
     * Setup Method.
     *
     * @param string $sampleFile
     */
    protected function setUp()
    {
        $sampleFile = __DIR__ . '/../TestFiles/sample.pdf';
        $this->instance = new Pdf2text($sampleFile, [
            'convertQuotes'    => ENT_QUOTES,
            'multibyteUnicode' => 4,
        ]);
    }

    /**
     * Test Decode Method.
     */
    public function testDecode()
    {
        $result   = $this->instance->decode();
        $expected = 'My dog is not like other dogs.'
                  . 'He doesn\'t care to walk,'
                  . 'He doesn\'t bark, he doesn\'t howl.'
                  . 'He goes "Tick, tock. Tick, tock."' . "\n";

        $this->assertEquals($expected, $result);
    }

    /**
     * Test No File Sent Exception Method.
     *
     * @expectedException \RuntimeException
     */
    public function testNoFileSentExceiption()
    {
        $failure = new Pdf2text();
    }

    /**
     * Test File Not Found Method.
     */
    public function testFileNotFound()
    {
        $not_found = new Pdf2text('nofile.pdf');
        $result    = $not_found->decode();
        $expected  = '';

        $this->assertEquals($expected, $result);
    }

}
