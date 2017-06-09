<?php
/**
 * AbstractDomParser.php
 *
 * @author Dietrich Rordorf
 * @since  06/2017
 */

namespace Rordi\PhpXml;

abstract class AbstractDomParser
{

    /** @var array  */
    protected $dictionary;

    /** @var array */
    protected $callbacks = [];

    /**
     * Parses a DOMDocument given the dictionary, using XPath translations to produce an associative array
     * that can then be mapped onto an entity or further processed
     *
     * @param \DOMDocument $doc
     * @param \DOMNode|null $context
     * @return array|mixed
     */
    protected function _parse_xml(\DOMDocument $doc, \DOMNode $context = null)
    {
        $metadata = array();

        //Loop the dictionary and try to obtain individual metadata entries
        foreach ($this->dictionary as $key => $instructions) {

            $metadata[$key] = array();

            // flag to flatten array produced produced by dictionary entry, defaults to true
            $flatten = isset($instructions['flatten']) ? $instructions['flatten'] : true;

            //STEP 1: match all nodes in the document
            if (isset($instructions['xpath'])) {
                $nodes = $this->_query_by_xpath($doc, $instructions['xpath'], $context);
            } else {
                $namespace = isset($instructions['namespace']) ? $instructions['namespace'] : null;
                $nodes = $this->_query_by_tagname($doc, $instructions['translation'], $namespace);
            }

            //STEP 2: process matched DOM nodes with callback
            if (isset($instructions['process']))
            {
                $val = null;

                if (is_callable($instructions['process'])) {

                    // $instructions['process'] is a callback
                    if ($this->_callback_expects_nodelist($instructions['process'])) {
                        $val = $instructions['process']($nodes);
                    } else {
                        if (get_class($nodes) == 'DOMNodeList') {
                            $val = [];
                            foreach ($nodes as $node) {
                                $val = $instructions['process']($node);
                            }
                        } else {
                            $val = $instructions['process']($nodes);
                        }
                    }

                } elseif (array_key_exists($instructions['process'], $this->callbacks)) {

                    // $instructions['process'] was registered as a callback
                    if ($this->_callback_expects_nodelist( $this->callbacks[$instructions['process']] )) {
                        $val = $this->callbacks[$instructions['process']]($nodes);
                    } else {
                        if (get_class($nodes) == 'DOMNodeList') {
                            $val = [];
                            foreach ($nodes as $node) {
                                $val = $this->callbacks[$instructions['process']]($node);
                            }
                        } else {
                            $val = $this->callbacks[$instructions['process']]($nodes);
                        }
                    }
                } else {

                    // a few common callbacks
                    switch($instructions['process']) {

                        //sub-parse node (or node list) with defined sub-dictionary
                        case 'parse':
                            if (get_class($nodes) == 'DOMNodeList') {
                                $val = [];
                                foreach ($nodes as $node) {
                                    $parser = new XmlParser($instructions['dictionary']);
                                    $val[] = $parser->parse($doc, $node);
                                }
                            }
                            elseif (get_class($nodes) == 'DOMNode') {
                                $parser = new XmlParser($instructions['dictionary']);
                                $val[] = $parser->parse($doc, $nodes);
                            } else {
                                error_log("WARNING: Skipping unexpected type " . get_class($nodes) . " - expected DOMNode or DOMNodeList, got: " . var_export($nodes), 0);
                            }
                            break;

                        //force value to be a boolean
                        case 'bool':
                            if (get_class($nodes) == 'DOMNodeList') {
                                $val = [];
                                foreach ($nodes as $node) {
                                    $val[] = ($node->nodeValue === true || $node->nodeValue == 'true' || $node->nodeValue == 1) ? true : false;
                                }
                            } elseif (get_class($nodes) == 'DOMNode') {
                                $val = ($nodes->nodeValue === true || $nodes->nodeValue === 'true' || $nodes->nodeValue === 1) ? true : false;
                            } else {
                                $val = false;
                            }
                            break;

                        //convert datetime string to DateTime object
                        case 'datetime':
                            if (get_class($nodes) == 'DOMNodeList') {
                                $val = [];
                                foreach ($nodes as $node) {
                                    $val = new \DateTime($node->nodeValue);
                                }
                            } elseif (get_class($nodes) == 'DOMNode') {
                                $val = new \DateTime($nodes->nodeValue);
                            } else {
                                $val = null;
                            }
                            break;

                        case 'merge':
                        case 'merge_comma':
                        case 'merge_semicolon':
                        case 'merge_point':
                            switch($instructions['process']) {
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

                            if (get_class($nodes) == 'DOMNodeList') {
                                $val = [];
                                foreach ($nodes as $node) {
                                    $val[] = $node->nodeValue;
                                }
                                $val = implode($separator, $val);
                                trim($val);
                            } elseif (get_class($nodes) == 'DOMNode') {
                                $val = $nodes->nodeValue;
                            } else {
                                $val = null;
                            }
                            break;
                    }
                }

                if ($flatten) {
                    $val = $this->_flatten_array($val);
                }
                array_push($metadata[$key], $val);

            } else {

                //transform $metadata[$key] to be array if a string value is already there
                if (isset($metadata[$key]) and !empty($metadata[$key]) and !is_array($metadata[$key])) {
                    $val = $metadata[$key];
                    unset($metadata[$key]);
                    $metadata[$key][] = $val;
                }

                //if $nodes is a DOMNodeList loop all nodes and push to end of array
                if (isset($nodes->length)) {

                    if (!is_array($metadata[$key])) {
                        $metadata[$key] = array();
                    }

                    foreach ($nodes as $node) {
                        if (isset($node->nodeValue)) {
                            array_push($metadata[$key], trim($node->nodeValue));
                        }
                    }
                } elseif (is_array($metadata[$key])) {
                    //if $metadata[$key] is an array, add node to array
                    $metadata[$key][] = $nodes;
                } else {
                    //if a simple value just assign it to metadata
                    $metadata[$key] = $nodes;
                }
            }

            // reset nodes and xpath variables
            try {
                unset($nodes, $node, $xpath, $val);
            } catch (\Exception $e) {
                // ignore - var might not have been set
            }

        } //end foreach ()

        $metadata = $this->_flatten_array($metadata);

        return $metadata;
    }



    /*
     * private scope
     */



    /**
     * Query the DOM using the xpath expression, optionally constraining the query withint a DomNode context
     *
     * @param \DOMDocument $doc
     * @param $xpath
     * @param null $context
     * @return mixed
     */
    private function _query_by_xpath(\DOMDocument $doc, $xpath, $context = null)
    {
        $domXpath = new \DOMXPath($doc);
        if (empty($context)) {
            $nodes = $domXpath->evaluate($xpath);
        } else {
            $nodes = $domXpath->evaluate($xpath, $context);
        }
        return $nodes;
    }

    /**
     * Query the DOM using simply tag name, optionally restrain to tag names within a namespace
     *
     * @param \DOMDocument $doc
     * @param $namespace
     * @param $translation
     * @return \DOMNodeList
     */
    private function _query_by_tagname(\DOMDocument $doc, $translation, $namespace = null)
    {
        if ($namespace) {
            $nodes = $doc->getElementsByTagNameNS($namespace, $translation);
        } else {
            $nodes = $doc->getElementsByTagName($translation);
        }
        return $nodes;
    }

    /**
     * Flatten multi-dimensional arrays
     *
     * @param mixed $arr
     * @return mixed
     */
    private function _flatten_array($arr)
    {
        if (!is_array($arr)) {
            return $arr;
        }

        foreach ($arr as $key => $val) {

            //recursively flatten sub-arrays first
            if (is_array($val)) {
                $arr[$key] = $this->_flatten_array($val);
            }

            //if val is an array of length 1, convert to string
            if (is_array($val) && count($val)==1) {
                $arr[$key] = array_values($val)[0];
            }

            //if val is an array of length 0, unset
            if (is_array($val) && count($val)==0) {
                unset($arr[$key]);
            }

            //if val is an array that contains an array with only an empty value, set as null, unless the $key is not a number
            if (is_int($key) && is_array($val) && count($val)==1 && array_values($val)[0]==null) {
                unset($arr[$key]);
                $arr[$key] = null;
            }
        }

        if (is_array($arr) && count($arr)==1 && is_int(current(array_keys($arr)))) {
            $arr = array_values($arr)[0];
        }

        return $arr;
    }

    /**
     * Check if a callback expects a DomNodeList (default: expecting a DomNode)
     *
     * @param $callback
     * @return bool
     */
    private function _callback_expects_nodelist($callback)
    {
        $cref = new \ReflectionFunction($callback);
        $params = $cref->getParameters();
        if (count($params) > 0) {
            $param = array_values($params)[0];
            if ($param->name == 'nodes') {
                return true;
            }
        }
        return false;
    }

}
