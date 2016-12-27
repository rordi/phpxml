<?php
/**
 * XmlParser.php
 *
 * @author Dietrich Rordorf
 * @since  12/2016
 */

namespace Rordi\PhpXml;


class XmlParser
{

    /** @var array  */
    private $dictionary;

    /** @var array */
    private $callbacks = [];


    /*
     * public scope
     */

    /**
     * XmlParser constructor.
     * @param array $dictionary
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



    /*
     * private scope
     */

    /**
     * Parses a DOMDoducment / XML file, given the dictionary, using XPath translations to produce an associative array
     * that can then be mapped onto an entity
     *
     * @param \DOMDocument $doc
     * @param \DOMNode|null $context
     * @return array|mixed
     */
    private function _parse_xml(\DOMDocument $doc, \DOMNode $context = null) {

        $metadata = array();

        //Loop the dictionary and try to obtain individual metadata entries
        foreach($this->dictionary as $key => $properties)
        {
            $metadata[$key] = array();

            //STEP 1: match all nodes in the document
            if(isset($properties['xpath']))
            {
                //query using the xpath expression set in the dictionary
                $xpath = new \DOMXPath($doc);
                if(empty($context))
                {
                    $nodes = $xpath->evaluate($properties['xpath']);
                }
                else
                {
                    $nodes = $xpath->evaluate($properties['xpath'], $context);
                }
            }
            else
            {
                //simply query by tag name
                if(isset($properties['namespace']))
                {
                    $nodes = $doc->getElementsByTagNameNS($properties['namespace'], $properties['translation']);
                }
                else
                {
                    $nodes = $doc->getElementsByTagName($properties['translation']);
                }
            }

            //STEP 2: process matched DOM nodes with callback
            if(isset($properties['process']))
            {
                $val = null;

                if(is_callable($properties['process'])) // $properties['process'] is a callback
                {
                    if($this->_callback_expects_nodelist($properties['process']))
                    {
                        $val = $properties['process']($nodes);
                    }
                    else
                    {
                        if(get_class($nodes) == 'DOMNodeList')
                        {
                            $val = [];
                            foreach($nodes as $node)
                            {
                                $val = $properties['process']($node);
                            }
                        }
                        else
                        {
                            $val = $properties['process']($nodes);
                        }
                    }
                }
                elseif(array_key_exists($properties['process'], $this->callbacks)) // $properties['process'] was registered as a callback
                {
                    if($this->_callback_expects_nodelist( $this->callbacks[$properties['process']] ))
                    {
                        $val = $this->callbacks[$properties['process']]($nodes);
                    }
                    else
                    {
                        if(get_class($nodes) == 'DOMNodeList')
                        {
                            $val = [];
                            foreach($nodes as $node)
                            {
                                $val = $this->callbacks[$properties['process']]($node);
                            }
                        }
                        else
                        {
                            $val = $this->callbacks[$properties['process']]($nodes);
                        }
                    }
                }
                else // a few common callbacks
                {
                    switch($properties['process'])
                    {
                        //sub-parse node (or node list) with defined sub-dictionary
                        case 'parse':
                            if(get_class($nodes) == 'DOMNodeList')
                            {
                                $val = [];
                                foreach($nodes as $node)
                                {
                                    $parser = new XmlParser($properties['dictionary']);
                                    $val[] = $parser->parse($doc, $node);
                                }
                            }
                            elseif(get_class($nodes) == 'DOMNode')
                            {
                                $parser = new XmlParser($properties['dictionary']);
                                $val[] = $parser->parse($doc, $nodes);
                            }
                            else
                            {
                                error_log("WARNING: Skipping unexpected type " . get_class($nodes) . " - expected DOMNode or DOMNodeList, got: " . var_export($nodes), 0);
                            }
                            break;

                        //force value to be a boolean
                        case 'bool':
                            if(get_class($nodes) == 'DOMNodeList')
                            {
                                $val = [];
                                foreach ($nodes as $node)
                                {
                                    $val[] = ($node->nodeValue === true || $node->nodeValue == 'true' || $node->nodeValue == 1) ? true : false;
                                }
                            }
                            elseif(get_class($nodes) == 'DOMNode')
                            {
                                $val = ($nodes->nodeValue === true || $nodes->nodeValue === 'true' || $nodes->nodeValue === 1) ? true : false;
                            }
                            else
                            {
                                $val = false;
                            }
                            break;

                        //convert datetime string to DateTime object
                        case 'datetime':
                            if(get_class($nodes) == 'DOMNodeList')
                            {
                                $val = [];
                                foreach ($nodes as $node)
                                {
                                    $val = new \DateTime($node->nodeValue);
                                }
                            }
                            elseif(get_class($nodes) == 'DOMNode')
                            {
                                $val = new \DateTime($nodes->nodeValue);
                            }
                            else
                            {
                                $val = null;
                            }
                            break;

                        case 'merge':
                        case 'merge_comma':
                        case 'merge_semicolon':
                        case 'merge_point':
                            switch($properties['process'])
                            {
                                case 'merge_comma':
                                    $separator = ', ';
                                    break;
                                case 'merge_semicolon':
                                    $separator = '; ';
                                    break;
                                case 'merge_point':
                                    $separator = '. ';
                                    break;
                                default:
                                    $separator = ' ';
                            }
                            if(get_class($nodes) == 'DOMNodeList')
                            {
                                $val = [];
                                foreach($nodes as $node) {
                                    $val[] = trim($node->nodeValue);
                                }
                                $val = implode($separator, $val);
                                trim($val);
                            }
                            elseif(get_class($nodes) == 'DOMNode')
                            {
                                $val = $nodes->nodeValue;
                            }
                            else
                            {
                                $val = null;
                            }
                            break;
                    }
                }
                $val = $this->_flatten_array($val);
                array_push($metadata[$key], $val);
            }
            else
            {
                //transform $metadata[$key] to be array if a string value is already there
                if(isset($metadata[$key]) and !empty($metadata[$key]) and !is_array($metadata[$key]))
                {
                    $val = $metadata[$key];
                    unset($metadata[$key]);
                    $metadata[$key][] = $val;
                }

                //if $nodes is a DOMNodeList loop all nodes and push to end of array
                if(isset($nodes->length))
                {
                    if(!is_array($metadata[$key]))
                    {
                        $metadata[$key] = array();
                    }
                    foreach($nodes as $node)
                    {
                        if(isset($node->nodeValue))
                        {
                            array_push($metadata[$key], trim($node->nodeValue));
                        }
                    }
                }
                //if $metadata[$key] is an array, add node to array
                elseif(is_array($metadata[$key]))
                {
                    $metadata[$key][] = $nodes;
                }
                //if a simple value just assign it to metadata
                else
                {
                    $metadata[$key] = $nodes;
                }
            }

            //reset nodes and xpath variables
            if(isset($nodes)) unset($nodes);
            if(isset($node)) unset($node);
            if(isset($xpath)) unset($xpath);
            if(isset($val)) unset($val);

        } //end foreach()

        $metadata = $this->_flatten_array($metadata);
        return $metadata;

    }


    /**
     * Flatten multi-dimensional arrays
     *
     * @param mixed $arr
     * @return mixed
     */
    private function _flatten_array($arr)
    {
        if(!is_array($arr))
        {
            return $arr;
        }

        foreach($arr as $key => $val)
        {
            //recursively flatten sub-arrays first
            if(is_array($val))
            {
                $arr[$key] = $this->_flatten_array($val);
            }

            //if val is an array of length 1, convert to string
            if(is_array($val) && count($val)==1)
            {
                $arr[$key] = array_values($val)[0];
            }

            //if val is an array of length 0, unset
            if(is_array($val) && count($val)==0)
            {
                unset($arr[$key]);
            }

            //if val is an array that contains an array with only an empty value, set as null
            if(is_array($val) && count($val)==1 && array_values($val)[0]==null)
            {
                unset($arr[$key]);
                $arr[$key] = null;
            }
        }


        if(is_array($arr) && count($arr)==1)
        {
            $keys = array_keys($arr);
            if(!is_string($keys[0])) // keep named arrays
            {
                $arr = array_values($arr)[0];
            }
        }
        return $arr;
    }


    /**
     * @param $callback
     * @return bool
     */
    private function _callback_expects_nodelist($callback)
    {
        $cref = new \ReflectionFunction($callback);
        $params = $cref->getParameters();
        if(count($params) > 0)
        {
            $param = array_values($params)[0];
            if($param->name == 'nodes')
            {
                return true;
            }
        }
        return false;
    }



}