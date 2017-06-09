<?php
/**
 * XmlParser.php
 *
 * @author Dietrich Rordorf
 * @since  12/2016
 * @see    https://github.com/rordi/phpxml
 */
namespace Rordi\PhpXml;

class XmlParser extends \Rordi\PhpXml\AbstractDomParser
{

    /**
     * @param array $dictionary
     * @see https://github.com/rordi/phpxml
     */
    public function __construct(array $dictionary = [])
    {
        $this->dictionary = $dictionary;
    }

    /**
     * @return array
     */
    public function getDictionary()
    {
        return $this->dictionary;
    }

    /**
     * @param array $dictionary
     * @return XmlParser
     */
    public function setDictionary($dictionary)
    {
        $this->dictionary = $dictionary;
        return $this;
    }

    /**
     * @param string $name
     * @param callable $callback
     * @return XmlParser
     */
    public function addCallback($name, callable $callback)
    {
        $this->callbacks[$name] = $callback;
        return $this;
    }

    /**
     * @param \DOMDocument $doc
     * @param \DOMNode|null $context
     * @return array|mixed
     */
    public function parse(\DOMDocument $doc, \DOMNode $context = null)
    {
        return $this->_parse_xml($doc, $context);
    }

}
