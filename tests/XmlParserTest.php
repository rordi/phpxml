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
            <published>10 March 2016</published>
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



    public function testParseXml()
    {
        $doc = new \DOMDocument();
        $doc->strictErrorChecking = false;
        $doc->loadXML($this->xml);

        $parser = new \Rordi\PhpXml\XmlParser();
        $parser->setDictionary($this->dictionary);
        $data = $parser->parse($doc);

        $expected = [
            'type' => 'article',
            'title' => 'A simple title',
            'main_author' => [
                'givenname' => 'Firstname',
                'surname' => 'Lastname'
            ],
        ];

        $this->assertSame($expected, $data);
    }

    public function testTimeCallback()
    {
        $doc = new \DOMDocument();
        $doc->strictErrorChecking = false;
        $doc->loadXML($this->xml);

        $dic = [
            'published' => [
                'xpath' => '//published',
                'process' => 'datetime'
            ]
        ];

        $parser = new \Rordi\PhpXml\XmlParser();
        $parser->setDictionary($dic);
        $data = $parser->parse($doc);

        $published = new \DateTime();
        $published->setTimestamp(strtotime('10 March 2016'));
        $expected = $published->getTimestamp();

        $this->assertInstanceOf(\DateTime::class, $data['published']);
        $this->assertSame($expected, $data['published']->getTimestamp());
    }


    public function testMergeCallback()
    {
        $doc = new \DOMDocument();
        $doc->strictErrorChecking = false;
        $doc->loadXML($this->xml);

        $separators = [
            'merge' => ' ',
            'merge_comma' => ', ',
            'merge_semicolon' => '; ',
            'merge_point' => '. ',
        ];

        foreach ($separators as $callback => $separator)
        {
            $dic = [
                'last_author' => [
                    'xpath' => "//authors/author[@role='secondary']",
                    'process' => 'parse',
                    'dictionary' => [
                        'names' => [
                            'xpath' => './first | ./last',
                            'process' => $callback
                        ],
                    ]
                ],
            ];

            $expected = "Firstname 2{$separator}Lastname 2";

            $parser = new \Rordi\PhpXml\XmlParser();
            $parser->setDictionary($dic);
            $data = $parser->parse($doc);

            $this->assertSame($expected, $data['last_author']);
        }
    }

}