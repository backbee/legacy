<?php

/*
 * Copyright (c) 2022 Obione
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

namespace BackBee\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

use BackBee\Console\AbstractCommand;

/**
 * Create new website entries.
 *
 * @category    BackBee
 *
 *
 * @author      Bogdan Oanes <bogdan.oanes@lp-digital.fr>
 */
class CreateWebsiteCommand extends AbstractCommand
{
    /**
     * Input interface
     * @var \Symfony\Component\Console\Output\InputInterface
     */
    private $input;

    /**
     * Output interface
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * The current BackBee application
     * @var \BackBee\BBApplication
     */
    private $bbapp;

    /**
     * Output interface
     * @var \Doctrine\ORM\EntityManager
     */
    private $entyMgr;

    /**
     * Site label
     * @var string
     */
    private $siteLabel;

    /**
     * Site domain
     * @var string
     */
    private $siteDomain;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('bbapp:create_site')
            ->addOption('site_label', 'site_label', InputOption::VALUE_REQUIRED, 'site label.')
            ->addOption('site_domain', 'site_domain', InputOption::VALUE_REQUIRED, 'site domain.')
            ->setDescription('Create new website.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> updates app:

   <info>php bbapp:create</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('site_label') || !$input->getOption('site_domain')) {
            throw new \InvalidArgumentException('Both options `site_label` and `site_domain` are required.');
        }

        try {
            $this
                ->init($input, $output)
                ->runSitesYmlProcess()
                ->runLayoutsGenerationProcess()
            ;
        } catch (\Exception $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');
            return 1;
        }

        return 0;
    }

    /**
     * Init main tools
     *
     * @return CreateWebsiteCommand
     */
    protected function init(InputInterface $input, OutputInterface $output)
    {
    	$this->input = $input;
    	$this->output = $output;

    	$this->bbapp = $this->getApplication()->getApplication();
    	$this->entyMgr = $this->bbapp->getEntityManager();

    	$this->siteLabel = $this->input->getOption('site_label');

        if (filter_var($this->input->getOption('site_domain'), FILTER_VALIDATE_URL)) {
            $urlData = parse_url($this->input->getOption('site_domain'));

            if(!empty($urlData['host']) && $urlData['host'] !== null) {
                $this->siteDomain = $urlData['host'].(empty($urlData['port']) ? '' : ':'.$urlData['port']).(empty($urlData['path']) ? '' : $urlData['path']);
            }
        } else {
            throw new \InvalidArgumentException('Invalid site domain format. Example of valid domain: http://backbee.com');
        }

    	return $this;
    }

    /**
     * Create new site entry inside sites.yml file
     *
     * @return CreateWebsiteCommand
     */
    protected function runSitesYmlProcess()
    {
        $sitesConf = $this->bbapp->getConfig()->getSitesConfig();

        if ($this->siteDomain === null) {
            throw new \InvalidArgumentException('This site domain is not valid (empty value)');
        }

        if (is_array($sitesConf) && array_key_exists($this->siteLabel, $sitesConf)) {
            throw new \InvalidArgumentException('This label `'.$this->siteLabel.'` already present in sites.yml');
        }

        $newSite = [
            \BackBee\Util\StringUtils::urlize($this->siteLabel) => [
                'label'  => $this->siteLabel,
                'domain' => $this->siteDomain,
            ],
        ];

        $newSitesConf = (is_array($sitesConf)) ? array_merge($sitesConf, $newSite) : $newSite;
        file_put_contents(
                $this->bbapp->getBaseDir().DIRECTORY_SEPARATOR.'repository/Config/sites.yml',
                Yaml::dump($newSitesConf)
        );

        return $this;
    }

    /**
     * Create new site db entry, home layout, root page mediacenter and keyword
     *
     * @return CreateSudoerCommand
     */
    protected function runLayoutsGenerationProcess()
    {
        # Website creation
        if (null === $site = $this->entyMgr->find('BackBee\Site\Site', md5($this->siteLabel))) {
            $site = $this->createSite($this->siteLabel, $this->siteDomain);
        }
        $this->bbapp->getContainer()->set('site', $site);

        # Home layout
        if (null === $layout = $this->entyMgr->find('BackBee\Site\Layout', md5('defaultlayout-' . $this->siteLabel))) {
            $layout = $this->createHomeLayout($site);
        }

        # Creating site root page
        if (null === $root = $this->entyMgr->find('BackBee\NestedNode\Page', md5('root-' . $this->siteLabel))) {
            $root = $this->createRootPage($site, $layout);
        }

        // Creating mediacenter root
        if (null === $mediafolder = $this->entyMgr->find('BackBee\NestedNode\MediaFolder', md5('media'))) {
            $this->createMediaFolder();
        }

        // Creating keyword
        if (null === $this->entyMgr->find('BackBee\NestedNode\KeyWord', md5('root'))) {
            $this->createKeyword();
        }

        return $this;
    }

    /**
     * Create mediafolder
     */
    private function createMediaFolder()
    {
        $mediafolder = new \BackBee\NestedNode\MediaFolder(md5('media'));
        $mediafolder->setTitle('Mediacenter')->setUrl('/');
        $this->entyMgr->persist($mediafolder);
        $this->entyMgr->flush($mediafolder);
    }

    /**
     * Create keywords
     */
    private function createKeyword()
    {
        $keyword = new \BackBee\NestedNode\KeyWord(md5('root'));
        $keyword->setRoot($keyword);
        $keyword->setKeyWord('root');
        $this->entyMgr->persist($keyword);
        $this->entyMgr->flush($keyword);
    }

    /**
     * Create new site db entry
     *
     * @param  string   $siteLabel      Site label
     * @param  string   $siteDomain     Site domain
     *
     * @return \BackBee\Site\Site
     */
    private function createSite($siteLabel, $siteDomain)
    {
        $site = new \BackBee\Site\Site(md5($this->siteLabel));
        $site
            ->setLabel($siteLabel)
            ->setServerName($siteDomain)
        ;
        $this->entyMgr->persist($site);
        $this->entyMgr->flush($site);

        return $site;
    }

    /**
     * Create new root page
     *
     * @param  \BackBee\Site\Site       $site       Site object
     * @param  \BackBee\Site\Layout     $layout     Layout object
     *
     * @return \BackBee\NestedNode\Page
     */
    private function createRootPage($site, $layout)
    {
        $pagebuilder = $this->bbapp->getContainer()->get('pagebuilder');
        $pagebuilder
            ->setUid(md5('root-' . $site->getLabel()))
            ->setTitle('Home')
            ->setLayout($layout)
            ->setSite($site)
            ->setUrl('/')
            ->putOnlineAndHidden()
        ;

        $page = $pagebuilder->getPage();
        $this->entyMgr->persist($page);
        $this->entyMgr->flush($page);

        return $page;
    }

    /**
     * Create new home layout
     *
     * @param  \BackBee\Site\Site       $site       Site object
     *
     * @return \BackBee\Site\Layout
     */
    private function createHomeLayout($site)
    {
        $layout = new \BackBee\Site\Layout(md5('defaultlayout-' . $site->getLabel()));
        $layout
            ->setData('{"templateLayouts":[{"title":"Top column","layoutSize":{"height":300,"width":false},"gridSizeInfos":{"colWidth":60,"gutterWidth":20},"id":"Layout__1332943638139_1","layoutClass":"bb4ResizableLayout","animateResize":false,"showTitle":false,"target":"#bb5-mainLayoutRow","resizable":true,"useGridSize":true,"gridSize":5,"gridStep":100,"gridClassPrefix":"span","selectedClass":"bb5-layout-selected","position":"none","height":800,"defaultContainer":"#bb5-mainLayoutRow","layoutManager":[],"mainZone":true,"accept":[],"maxentry":"0","defaultClassContent":null},{"title":"Main column","layoutSize":{"height":800,"width":false},"gridSizeInfos":{"colWidth":60,"gutterWidth":20},"id":"Layout__1383430750637_1","layoutClass":"bb5-resizableLayout","animateResize":false,"showTitle":false,"target":"#bb5-mainLayoutRow","resizable":true,"useGridSize":true,"gridSize":2,"gridStep":100,"gridClassPrefix":"span","selectedClass":"bb5-layout-selected","alphaClass":"alpha","omegaClass":"omega","typeClass":"hChild","clearAfter":1,"height":800,"defaultContainer":"#bb5-mainLayoutRow","layoutManager":[],"mainZone":false,"accept":[],"maxentry":0,"defaultClassContent":null},{"title": "Nouvelle zone","layoutSize": {"height": 800,"width": false},"gridSizeInfos": {"colWidth": 60,"gutterWidth": 20},"id": "Layout__1383430750640_1","layoutClass": "bb5-resizableLayout","animateResize": false,"showTitle": false,"target": "#bb5-mainLayoutRow","resizable": true,"useGridSize": true,"gridSize": 2,"gridStep": 100,"gridClassPrefix": "span","selectedClass": "bb5-layout-selected","alphaClass": "alpha","omegaClass": "omega","typeClass": "hChild","clearAfter": 1,"height": 800,"defaultContainer": "#bb5-mainLayoutRow","layoutManager": [],"mainZone": false,"accept": [],"maxentry": 0,"defaultClassContent": null}]}')
            ->setLabel('Home')
            ->setPath('Home.twig')
            ->setPicPath($layout->getUid() . '.png')
            ->setSite($site)
        ;
        $this->entyMgr->persist($layout);

        return $layout;
    }
}