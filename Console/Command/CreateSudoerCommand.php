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
 * @copyright   Lp digital system
 * @author      Bogdan Oanes <bogdan.oanes@lp-digital.fr>
 */
class CreateSudoerCommand extends AbstractCommand
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
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('bbapp:create_sudoer')
            ->addOption('user_name', 'user_name', InputOption::VALUE_OPTIONAL, 'username.')
            ->addOption('user_password', 'user_password', InputOption::VALUE_OPTIONAL, 'user password.')
            ->addOption('user_email', 'user_email', InputOption::VALUE_OPTIONAL, 'user email.')
            ->setDescription('Create new sudoer.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> creates new admin user:

   <info>php bbapp:create:sudoer</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
    	$this
            ->init($input, $output)
            ->runInsertSudoerProcess()
	;
    }       

    /** 
     * Init main tools
     *
     * @return CreateSudoerCommand
     */
    protected function init(InputInterface $input, OutputInterface $output)
    {
    	$this->input = $input;
    	$this->output = $output;

    	$this->bbapp = $this->getContainer()->get('bbapp');
    	$this->entyMgr = $this->bbapp->getEntityManager();

    	return $this;
    }

    /**
     * Insert the user in DB and security.yml file
     *
     * @return CreateSudoerCommand
     */
    protected function runInsertSudoerProcess() 
    {
        if (!$this->input->getOption('user_name') || !$this->input->getOption('user_password') || !$this->input->getOption('user_email')) {
            $this->output->writeln('<info>You have to specify all option in order to insert a new superadmin user (--user_name, --user_password, --user_email)</info>');
            return $this;
        }

        # Check if user already exists in db
        if (null === $adminUser = $this->entyMgr
                        ->getRepository('BackBee\Security\User')
                        ->findOneBy(array('_login' => $this->input->getOption('user_name')))) {
            $adminUser = $this->createUser(
                            $this->input->getOption('user_name'),
                            $this->input->getOption('user_password'),
                            $this->input->getOption('user_email')
                        );
            $this->output->writeln('<info>New user created.</info>');
        }

        # Recreate security.yml
        $securityConf = $this->bbapp->getConfig()->getSecurityConfig();
        if (!array_key_exists($adminUser->getLogin(), $securityConf['sudoers'])) {
            $securityConf['sudoers'][$adminUser->getLogin()] = $adminUser->getId();

            file_put_contents(
                $this->bbapp->getBaseDir().DIRECTORY_SEPARATOR.'repository/Config/security.yml',
                Yaml::dump($securityConf)
            );

            $this->output->writeln('<info>User added in security.yml file.</info>');
        }

        return $this;
    }

    /**
     * Create new superadmin user
     *
     * @param  string   $login         User name
     * @param  string   $password      User password
     * @param  string   $email         User email
     *
     * @return \BackBee\Security\User   The user object
     */
    private function createUser($login, $password, $email)
    {
        $encoderFactory = $this->bbapp->getContainer()->get('security.context')->getEncoderFactory();

        $adminUser = new \BackBee\Security\User(
            $login,
            $password,
            'SuperAdmin',
            'SuperAdmin'
        );

        $adminUser
            ->setApiKeyEnabled(true)
            ->setActivated(true)
        ;

        $encoder = $encoderFactory->getEncoder($adminUser);
        $adminUser
            ->setPassword($encoder->encodePassword($password, ''))
            ->setEmail($email)
            ->generateRandomApiKey()
        ;

        $this->entyMgr->persist($adminUser);
        $this->entyMgr->flush($adminUser);

        return $adminUser;
    }
}