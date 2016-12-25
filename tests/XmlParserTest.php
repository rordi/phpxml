<?php
/**
 * XmlParserTest.php
 *
 * @author Dietrich Rordorf
 * @since  12/2016
 */


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
                This is the body of the article.
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
    ];

    private $expected =   [
        'type' => 'article',
        'title' => 'A simple title',
        'main_author' => [
            'givenname' => 'Firstname',
            'surname' => 'Lastname'
        ]
    ];


    public function testParseXml()
    {
        $doc = new \DOMDocument();
        $doc->strictErrorChecking = false;
        $doc->loadXML($this->xml);

        $parser = new \Rordi\PhpXml\XmlParser();
        $parser->setDictionary($this->dictionary);
        $data = $parser->parse($doc);

        $this->assertSame($this->expected, $data);
    }

}