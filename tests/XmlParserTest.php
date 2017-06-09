<?php
/**
 * XmlParserTest.php
 *
 * @author Dietrich Rordorf
 * @since  12/2016
 */

namespace Rordi\Tests;

use Rordi\PhpXml\XmlParser;

class XmlParserTest extends \PHPUnit_Framework_TestCase
{

    private $xml = '
        <document>
            <type class="article" />
            <title lang="en">A simple title</title>
            <title lang="de">Ein einfacher Title</title>
            <authors>
                <author role="main">
                    <first>Firstname</first>
                    <last>Lastname</last>
                </author>
                <author role="secondary">
                    <first>Firstname 2</first>
                    <last>Lastname 2</last>
                </author>
            </authors>
            <body>
                <chapters>
                    <chapter>
                        <title>Chapter 1</title>
                        <content>This is the body of the article.</content>
                    </chapter>
                </chapters>
            </body>
        </document>
    ';

    private $dictionary = [
        'type' => [
            'xpath' => '/document/type/@class'
        ],
        'title' => [
            'xpath' => "//title[@lang='en']"
        ],
        'main_author' => [
            'xpath' => "//authors/author[@role='main']",
            'process' => 'parse',
            'dictionary' => [
                'givenname' => [
                    'xpath' => './first',
                ],
                'surname' => [
                    'xpath' => './last',
                ],
            ]
        ],
        'chapters' => [
            'xpath' => "//document/body/chapters/chapter",
            'flatten' => false,
            'process' => 'parse',
            'dictionary' => [
                'title' => [
                    'xpath' => './title',
                ],
            ]
        ]
    ];

    private $expected =   [
        'type' => 'article',
        'title' => 'A simple title',
        'main_author' => [
            'givenname' => 'Firstname',
            'surname' => 'Lastname'
        ],
        'chapters' => [
            0 => [
                'title' => 'Chapter 1'
            ]
        ]
    ];


    public function testParseXml()
    {
        $doc = new \DOMDocument();
        $doc->strictErrorChecking = false;
        $doc->loadXML($this->xml);

        $parser = new XmlParser();
        $parser->setDictionary($this->dictionary);
        $data = $parser->parse($doc);

        var_dump($data);
        ob_flush();
        exit;



        $this->assertSame($this->expected, $data);
    }

}
