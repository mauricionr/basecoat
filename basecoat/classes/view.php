<?php

namespace Basecoat;

/**
* Provides template processing functionality
*
* @author Brent Baisley <brent@bigstockphoto.com>
*/
class View
{
    /**
    * Layout template to use
    */
    public $layouts = array();
    public $layout = null;

    public $templates_path = null;

    /**
    * Namespace to place content in if none is specified
    */
    public $default_namespace = 'body';

    /**
    * Name/value pairing of data tags available to templates
    */
    public $data = array();

    /**
    * Regular expression to use to parse out block tags
    * Default block tag structure is:
    *   @block_name
    *
    */
    public $block_tag_regex = '/^@(\S+)>[\r\n]/m';

    /**
    * Content "blocks" in template files
    */
    public $blocks = array();

    /**
    * Enable data tag search and replace
    */
    public $enable_data_tags = true;

    /**
    * Data tag delimiters
    */
    public $data_tags_delimiters = array(
        'prefix'  => '{{:',
        'suffix'  => '}}',
    );

    /**
    * Create an instance of the Content class
    *
    * @return Object instance of Content class
    */
    public function __construct() {}

    /*
    * Load the list of layouts. An associative array where the key is the layout name
    * and the value is the relative path to the layout file from the templates directory.
    * Optionally pass name of default layout.
    *
    * @param Array $layouts associative array of layouts names and relative paths
    * @param String $default name of layout to set as default
    */
    public function setLayouts($layouts, $default = null)
    {
        $this->layouts = $layouts;
        if (!is_null($default)) {
            $this->setLayout($default);
        }
    }

    /*
    * Set the layout to use for output. Name must match one set with setLayouts().
    *
    * @param String $layout_name name of layout
    */
    public function setLayout($layout_name)
    {
        $this->layout = $layout_name;
    }

    /*
    * Get the relative path to a layout file. Default is layout set with setLayout().
    *
    * @param String $layout_name name of layout
    * @return String path to layout file relative  to templates path
    */
    public function getLayout($layout_name=null)
    {
        if (is_null($layout_name)) {
            $layout_name = $this->layout;
        }
        return $this->layouts[$layout_name];
    }

    /**
    * Path to templates directory. Use as a prefix when referencing templates and layouts.
    *
    * @param String $path valid directory path
    */
    public function setTemplatesPath($path)
    {
        $this->templates_path = $path;
    }

    /**
    * Getter method for returning a data item
    *
    * @param String $name Name of the data item to return
    * @return Mixed value of the data item
    */
    public function __get($name)
    {
        if ( !isset($this->data[$name]) ) {
            $this->data[$name] = null;
        }
        return $this->data[$name];
    }

    /**
    * Return data values as string
    *
    * @return String values of data items delimited with line feeds
    */
    public function __toString()
    {
        echo implode("\n", $this->data);
    }

    /**
    * Add content under the namespace
    * By default append to any existing data item with same namespace
    *
    * @param String $name namespace to add content under
    * @param Mixed $content content to any under the namespace, can be any data structure
    * @param Boolean $append whether or not to append the content to the namespace if namespace already exists
    */
    public function add($name, $content, $append=true)
    {
        if (isset($this->data[$name]) && $append) {
            $this->data[$name] .= $content;
        } else {
            $this->data[$name] = $content;
        }
        $this->$name = $this->data[$name];
    }

    /**
    * Add multiple content under multiple namespaces
    * Optionally provide a prefix to be prepended to each namespace name
    *
    * @param Array $name_vals array of name/value pairs to add
    * @param String $prefix prefix to prepend to each namespace name
    */
    public function multiadd($name_vals, $prefix=null)
    {
        foreach ($name_vals as $name => $val) {
            $this->add("{$prefix}{$name}", $val);
        }
    }

    /**
    * Search and replace data tags in templates
    * Allows usage of {{:data}} tags to output data
    * instead of having to use <?php echo $data;?>
    *
    * @param String $tpl template text to search and replace data tags on
    * @return String the processed template with data tags replaced
    */
    public function replaceDataTags(&$tpl)
    {
        if ( !$this->enable_data_tags ) {
            return $tpl;
        }
        if ( count($this->data)>0 ) {
            // create tags
            $makeTags = function (&$tag, $key, $tag_wrap) {
                $tag = $tag_wrap['prefix'].$tag.$tag_wrap['suffix'];
            };
            // Extract scalar variable
            $data_tags = array();
            foreach($this->data as $k=>$v) {
                if ( is_scalar($v) ) {
                    $data_tags[$k] = $v;
                }
            }
            $tag_keys = array_keys($data_tags);
            array_walk($tag_keys, $makeTags, $this->data_tags_delimiters);
            // search and replace data tags
            $tpl = str_replace($tag_keys, $data_tags, $tpl);
        }
        // cleanup any lingering tags
        //$this->stripDataTags($tpl);
        //$tpl = preg_replace('/{{:.[^}}]+}}/', '', $tpl);
        //return $tpl;

    }

    public function stripDataTags(&$tpl)
    {
        $tpl = preg_replace('/{{:.[^}}]+}}/', '', $tpl);
        //return $tpl;
    }

    /**
    * Add a content block under a specified namespace
    *
    * @param String $block_name namespace to add content block under
    * @param String $content content to add
    */
    public function addBlock($block_name, $content)
    {
        if (isset($this->blocks[$block_name])) {
            $this->blocks[$block_name] .= $content;
        } else {
            $this->blocks[$block_name] = $content;
        }
    }

    /**
    * Load and process a content template,
    * optionally parse content blocks into namespaces
    *
    * @param String $tpl template file to include
    * @param Boolean $parse whether to parse the template blocks into namespaces (default true)
    * @return String the processed template or the number of content blocks parsed
    */
    public function processTemplate($tpl, $parse=true)
    {
        if (file_exists($this->templates_path . $tpl)) {
            $tpl = $this->templates_path . $tpl;
        }
        if (!file_exists($tpl)) {
            return -1;
        }
        ob_start();
        include($tpl);
        $content = ob_get_clean();
        $this->replaceDataTags($content);
        if ( $parse ) {
            return $this->parseBlocks($content);
        } else {
            return $content;
        }
    }

    /**
    * Parse a template into content block namespaces
    * if content block identifiers are present
    *
    * @param String $tpl template to parse
    * @return Integer number of content blocks discovered
    */
    public function parseBlocks($tpl)
    {
        $tpl_blocks  = preg_split($this->block_tag_regex, ltrim($tpl), -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $blocks_parsed = count($tpl_blocks);
        if ( 1 == $blocks_parsed ) {
            $this->addBlock($this->default_namespace, $tpl_blocks[0]);
        } else {
            $blocks_parsed = $blocks_parsed/2;
            $namespace  = $this->default_namespace;
            foreach($tpl_blocks as $i=>$data) {
                if ( $i%2==0 ) {
                    if ( strlen($data)>30 ) {
                        $this->addBlock($namespace, $data);
                    } else {
                        $namespace = $data;
                    }
                } else {
                    $this->addBlock($namespace, $data);
                }
            }
        }
        return $blocks_parsed;
    }

    /**
    * Clear all content block namespaces and data
    */
    public function clear()
    {
        foreach($this->data as $namespace=>$data) {
            unset($this->$namespace);
        }
        $this->data  = array();
        $this->blocks = array();
    }

    /**
    * Get all the data currently loaded
    *
    * @return Array associative array of loaded data
    */
    public function getData()
    {
        return $this->data;
    }

    /**
    * Get all currently parsed content blocks
    *
    * @return Array associative array of content blocks
    */
    public function getBlocks()
    {
        return $this->blocks;
    }

    /**
    * Merge content blocks for this instance with passed instance
    *
    * @param \Basecoat\View $view Instance to add content blocks to
    * @return Integer number of content blocks merged
    */
    public function addToView($view)
    {
        $view->multiadd($this->blocks);
        return count($this->blocks);
    }

}