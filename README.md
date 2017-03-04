# phpxml

phpxml is a XML parser for DOMDocuments written in PHP.


## About

phpxml provides a conveninant interface to query and map XML documents loaded into PHP's DOMDocument to an associative PHP array for further processing (e.g. for serializing to an entity and persisting to a database). Querying the XML is done with XPath.


## Installation

Edit your composer.json file to add the GitHub repository:

~~~~ 
"repositories": [
    {
        "type": "git",
        "url": "https://github.com/rordi/phpxml.git"
    }
],
~~~~ 
    
Then, add phpxml as a dependency to your project:
~~~~ 
  composer require rordi/phpxml
~~~~ 


## Usage

Include the XmlParser namespace.

~~~~
  use Rordi\PhpXml\XmlParser;
~~~~

Initiate a new XmlParser with your dictionary.

~~~~
  $parser = new XmlParser()
  $parser->setDictionary($dic);   // $dic is an array, see below
  $data = $parser->parse($doc);   // $doc is a DOMDocument
~~~~ 


## Creating a dictionary

Given the following XML file:

~~~~ 
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
~~~~ 

You can produce the following associative array:

~~~~ 
[
  'type' => 'article',
  'title' => 'A simple title',
  'main_author' => [
    'givenname' => 'Firstname',
    'surname' => 'Lastname'
  ]
]
~~~~ 

With the following dictionary:

~~~~ 
$dictionary = [
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
~~~~ 

## Creating your own Parsers

Instead of setting the dictionary at runtime, you can easily create your own parsers that encapsulate the parsing logic and keeping your code clean. Simply write your own parser, which extends the XmlParser, and set it's dictionary in the __construct() method:

#### Exmaple Parser

~~~~ 
namespace App;

use Rordi\PhpXml\XmlParser;

class MyParser extends XmlParser 
{
    public function __construct()
    {
        $dictionary = [
            // your parser-specific dictionary
        ];
        parent::__construct($dictionary);
    }
}
~~~~ 

~~~~ 
    use App\MyParser;
    ...
    $myparser = new MyParser();
    $data = $myparser->parse($doc);
~~~~ 

## Working with callbacks

Often, when serializing or unserializing data, you may wish to e.g. instantiate a new DateTime from a date string, or force a value to be boolean or float, etc. To do this, you can work with callbacks in XmlParser.

In your dictionary, you can pass a callback through the field 'process'. XmlParser already includes the following callbacks: 'parse', 'datetime' and 'bool'. 'parse' will call the parser's parse_xml() method for the selected DOMNode (or DOMNodeList) and process it with the 'dictionary' (see 'main_author' in the above example dictionary). 

You can register your own callbacks with your parser:

~~~~ 
$parser = new XmlParser();
$parser->registerCallback('mytest', function($node) { $val = $node->nodeValue; /* some processing here */ return $val; });
~~~~ 

And then use your registered callback in the dictionary as 'process' => 'mytest'. 

You can make your callback to work with $node (single DOMNode) or with $nodes (DOMNodeList). Simply name the callback's paramter accordingly.

~~~~ 
function($node) {
    // I expect a single DOMNode
}

function($nodes) {
    // I expect a DOMNodeList, e.g. to loop as in foreach($nodes as $node) { ... }
}
~~~~ 
