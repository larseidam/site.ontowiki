<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki renderx view helper
 *
 * selects a template (e.g. based on the site:(class)template properties)
 * and render this template via partial
 *
 * @note: name is renderx since render already exists
 * @todo: use subClass hierarchy (mosts specific template + more generic template)
 *
 * @category OntoWiki
 * @package  OntoWiki_extensions_components_site
 */
class Site_View_Helper_Renderx extends Zend_View_Helper_Abstract implements Site_View_Helper_MarkupInterface
{
    /*
     * current view, injected with setView from Zend
     */
    public $view;

    /*
     * used erfurt model, taken from the view object
     */
    private $_model;

    /*
     * the default template (will be overwritten)
     */
    private $_template = '/types/default.phtml';

    /*
     * used templateData, taken from the view object
     * and overwritten if there is a new resource is given
     */
    public $templateData = array();

    /*
     * URI of the to rendered resource
     */
    private $_resourceUri;

    /*
     * an array of mappings (key = class URI, value = template name)
     */
    private $_mappings = null;

    /*
     * used schema URIs
     */
    const templatePropClass    = 'http://ns.ontowiki.net/SysOnt/Site/classTemplate';
    const templatePropResource = 'http://ns.ontowiki.net/SysOnt/Site/template';
    const typeProp             = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';

    /*
     * the main method, mentioned parameters are:
     * - template
     */
    public function renderx($options = array())
    {
        // if we have a template option this option wins
        if (isset($options['template'])) {
            $this->_template = $options['template'];
        } else {
            // try to query a template name
            $queriedTemplate = $this->selectTemplate();
            if ($queriedTemplate !== false) {
                $this->_template = $queriedTemplate;
            }
        }

        $this->prepareTemplateData();

        // try to do a partial or output error details
        try {
            $return = $this->view->partial($this->_template, $this->templateData);
        } catch (Exception $e) {
            $return = $this->renderError($e->getMessage());
        }
        return $return;
    }

    /*
     * render an error with an HTML5 details/summary block
     */
    private function renderError($errorMessage)
    {
        $summary = 'Error while trying to render "'.$this->resource->getTitle().'"';
        $return  = '<details><summary>'.$summary.'</summary>' . PHP_EOL;
        $return .= (string) $errorMessage . PHP_EOL;
        $return .= '</details>' . PHP_EOL;
    }

    /*
     * selects a template based on query results or keeps the default template
     */
    private function selectTemplate()
    {
        $description  = $this->getDescription();
        $templateName = null;

        // if we have specific template on the resource, use it
        if (isset($description[self::templatePropResource][0]['value'])) {
            $templateName   = $description[self::templatePropResource][0]['value'];
        } else {
            $this->getMappings();
            // try template hints on classes
            // try to map each rdf:type property value
            if (isset($description[self::typeProp])) {
                foreach ($description[self::typeProp] as $class) {
                    $classUri = $class['value'];
                    if (isset($this->_mappings[$classUri])) {
                        // overwrite, if class has an template entry
                        $templateName = $this->_mappings[$classUri];
                    }
                }
            }
        }

        if ($templateName != null) {
            $this->_template = $this->view->siteId .'/types/'. $templateName .'.phtml';
            return $this->_template;
        } else {
            return false;
        }
    }

    /*
     * prepares / overwrites the template data
     */
    private function prepareTemplateData()
    {
        $this->templateData['title']       = $this->resource->getTitle();
        $this->templateData['resourceUri'] = $this->_resourceUri;
        $this->templateData['description'] = $this->getDescription();
    }

    /*
     * returns or fetches and returns the mapping array
     */
    public function getMappings()
    {
        if ($this->_mappings == null) {
            // prepare the sparql query
            // this query should be very cacheable ...
            $query = '
                PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
                SELECT DISTINCT ?class ?template
                WHERE {
                    ?class <'. self::templatePropClass .'> ?template .
                    }';

            // fetch results
            $store = OntoWiki::getInstance()->erfurt->getStore();
            $result = $store->sparqlQuery($query);

            // fill the mappings array
            $this->_mappings = array();
            foreach ($result as $mapping) {
                $uri      = $mapping['class'];
                $template = $mapping['template'];
                $this->_mappings[$uri] = $template;
            }
        }

        return $this->_mappings;
    }

    /*
     * generates and return the description of $this->resourceUri
     */
    private function getDescription()
    {
        $this->resource     = new OntoWiki_Resource($this->_resourceUri, $this->_model);
        $this->description  = $this->resource->getDescription();
        $this->description  = $this->description[$this->_resourceUri];
        return $this->description;
    }

    /*
     * view setter (dev zone article: http://devzone.zend.com/article/3412)
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view         = $view;
        $this->_model       = $view->model;
        $this->templateData = $view->templateData;
        $this->_resourceUri = (string) $view->resourceUri;
    }

}
