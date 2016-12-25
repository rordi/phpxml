# phpxml

phpxml is a XML parser for DOMDocuments written in PHP.


## About

phpxml provides a conveninant interface to query and map XML documents loaded into PHP's DOMDocument to an associative PHP array for further processing (e.g. for serializing to an entity and persisting to a database). Querying the XML is done with XPath.


## Installation

Edit your composer.json file to add the GitHub repository:

    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/rordi/phpxml.git"
        }
    ],
    
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


## Creating a Dictionary

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
      ...
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

  $dic = [
    'type' => [
      'xpath' => '/document/type/@class'
    ].
    'title' => [
      'xpath' => "//title[@lang='en']"
    ],
    'main_author' => [
      'xpath' => "//author[@type='main']",
      'process' => 'parse',
      'dictionary' => [
        'givenname' => [
          'xpath' => './/firstname',
        ],
        'surname' => [
          'xpath' => './/lastname',
        ],
      ]
    ],
  ];
~~~~ 
