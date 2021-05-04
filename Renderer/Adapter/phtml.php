<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

namespace BackBee\Renderer\Adapter;

use BackBee\Controller\Exception\FrontControllerException;
use BackBee\Renderer\AbstractRenderer;
use BackBee\Renderer\AbstractRendererAdapter;
use BackBee\Renderer\Exception\RendererException;
use BackBee\Site\Layout;
use BackBee\Util\File\File;

use Exception;

/**
 * Rendering adapter for phtml templating files.
 *
 * @category    BackBee
 *
 * @copyright   Lp digital system
 * @author      c.rouillon <charles.rouillon@lp-digital.fr>
 *              e.chau <eric.chau@lp-digital.fr>
 */
class phtml extends AbstractRendererAdapter
{
    /**
     * Extensions to include searching file.
     *
     * @var array
     */
    protected $includeExtensions = [
        '.phtml',
        '.php',
    ];

    /**
     * @var array
     */
    private $params;

    /**
     * @var array
     */
    private $vars;

    /**
     * @param AbstractRenderer $renderer
     */
    public function __construct(AbstractRenderer $renderer, array $config = [])
    {
        parent::__construct($renderer, $config);

        $this->params = [];
        $this->vars = [];
    }

    /**
     * Magic method to get an assign var.
     *
     * @param string $var the name of the variable
     *
     * @return mixed the value
     */
    public function __get($var)
    {
        return isset($this->vars[$var]) ? $this->vars[$var] : null;
    }

    /**
     * Magic method to test the setting of an assign var.
     *
     * @codeCoverageIgnore
     *
     * @param string $var the name of the variable
     *
     * @return boolean
     */
    public function __isset($var)
    {
        return isset($this->vars[$var]);
    }

    /**
     * Magic method to assign a var.
     *
     * @codeCoverageIgnore
     * @param  string    $var   the name of the variable
     * @param  mixed     $value the value of the variable
     * @return AbstractRenderer the current renderer
     */
    public function __set($var, $value = null)
    {
        $this->vars[$var] = $value;

        return $this;
    }

    /**
     * @see BackBee\Renderer\RendererAdapterInterface::getManagedFileExtensions()
     */
    public function getManagedFileExtensions()
    {
        return $this->includeExtensions;
    }

    public function isValidTemplateFile($filename, array $templateDir)
    {
        if (0 === count($templateDir)) {
            return false;
        }

        File::resolveFilepath($filename, null, array('include_path' => $templateDir));

        return is_readable($filename);
    }

    public function renderTemplate($filename, array $templateDir, array $params = [], array $vars = [])
    {
        foreach ($params as $key => $v) {
            $this->setParam($key, $v);
        }

        foreach ($vars as $k => $v) {
            $this->assign($k, $v);
        }

        try {
            File::resolveFilepath($filename, null, array('include_path' => $templateDir));
            ob_start();
            include $filename;

            return ob_get_clean();
        } catch (FrontControllerException $fe) {
            ob_end_clean();
            throw $fe;
        } catch (Exception $e) {
            ob_end_clean();

            throw new RendererException(
                $e->getMessage().' in '.$filename, RendererException::RENDERING_ERROR, $e
            );
        }
    }

    /**
     * Assign one or more variables.
     *
     * @param  mixed     $var   A variable name or an array of variables to set
     * @param  mixed     $value The variable value to set
     * @return AbstractRenderer The current renderer
     */
    public function assign($var, $value = null)
    {
        if (is_string($var)) {
            $this->vars[$var] = $value;

            return $this;
        }

        if (is_array($var)) {
            foreach ($var as $key => $value) {
                $this->vars[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Return the current page to be rendered.
     *
     * @codeCoverageIgnore
     *
     * @return null|BackBee\NestedNode\Page
     */
    public function setParam($param, $value = null)
    {
        if (is_string($param)) {
            $this->params[$param] = $value;

            return $this;
        }

        if (is_array($param)) {
            foreach ($param as $key => $value) {
                $this->params[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Return parameters.
     *
     * @param string $param The parameter to return
     *
     * @return mixed The parameter value asked or array of the parameters
     */
    public function getParam($param = null)
    {
        if (null === $param) {
            return $this->params;
        }

        return isset($this->params[$param]) ? $this->params[$param] : null;
    }

    /**
     * @see BackBee\Renderer\RendererAdapterInterface::updateLayout()
     */
    public function updateLayout(Layout $layout, $layoutFile)
    {
        if (false === $layoutFile) {
            return false;
        }

        $mainLayoutRow = $layout->getDomDocument();
        if (false === $layout->isValid() || null === $mainLayoutRow) {
            throw new RendererException('Malformed data for the layout layout.');
        }

        // Add an php instruction to each final droppable zone found
        $xpath = new \DOMXPath($mainLayoutRow);
        $textNode = $mainLayoutRow->createTextNode('<?php echo $this->container()->first(); ?>');
        $nextNode = $mainLayoutRow->createTextNode('<?php echo $this->container()->next(); ?>');
        foreach ($xpath->query('//div[@class!="clear"]') as $node) {
            if (!$node->hasChildNodes()) {
                $node->appendChild(clone $textNode);
                $textNode = $nextNode;
            }
        }

        libxml_use_internal_errors(true);

        $domlayout = new \DOMDocument();
        $layoutcontent = str_replace(array('<?php', '?>'), array('&lt;?php', '?&gt;'), file_get_contents($layoutFile));
        @$domlayout->loadHTML($layoutcontent);
        $domlayout->formatOutput = true;

        $layoutNode = $domlayout->importNode($mainLayoutRow->firstChild, true);
        $layoutid = $layoutNode->getAttribute('id');

        $xPath = new \DOMXPath($domlayout);
        if (($targetNodes = $xPath->query('//div[@id="'.$layoutid.'"]')) && 0 < $targetNodes->length) {
            foreach ($targetNodes as $targetNode) {
                $targetNode->parentNode->replaceChild($layoutNode, $targetNode);
            }
        } elseif (($targetNodes = $domlayout->getElementsByTagName('body')) && 0 < $targetNodes->length) {
            foreach ($targetNodes as $targetNode) {
                $targetNode->appendChild($layoutNode);
            }
        } else {
            $domlayout->appendChild($layoutNode);
        }

        if (!file_put_contents($layoutFile, preg_replace_callback('/(&lt;|<)\?php(.+)\?(&gt;|>)/iu', create_function('$matches', 'return "<?php".html_entity_decode(urldecode($matches[2]))."?".">";'), $domlayout->saveHTML()))) {
            throw new RendererException(sprintf('Unable to save layout %s.', $layoutFile), RendererException::LAYOUT_ERROR);
        }

        libxml_clear_errors();

        return $layoutFile;
    }

    /**
     * @see BackBee\Renderer\RendererAdapterInterface::onRestorePreviousRenderer()
     */
    public function onRestorePreviousRenderer(AbstractRenderer $renderer)
    {
        parent::onRestorePreviousRenderer($renderer);

        $this->vars = $renderer->getAssignedVars();
        $this->params = $renderer->getParam();
    }
}
