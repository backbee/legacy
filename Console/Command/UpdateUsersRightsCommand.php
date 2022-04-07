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

use BackBee\BBApplication;
use BackBee\Console\AbstractCommand;
use BackBee\Security\Group;
use BackBee\Security\Acl\Permission\MaskBuilder;
use BackBee\Security\User;
use BackBee\Util\Collection\Collection;
use BackBee\Site\Site;
use BackBee\Site\Layout;
use BackBee\NestedNode\Page;
use BackBee\NestedNode\MediaFolder;
use BackBee\ClassContent\AbstractClassContent;

use Doctrine\ORM\EntityManager;

use Symfony\Component\Security\Acl\Dbal\Schema;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Security\Acl\Dbal\MutableAclProvider;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Update base users right command
 * @author Adrien Loiseau <aloiseau@nextinteractive.fr>
 */
class UpdateUsersRightsCommand extends AbstractCommand
{

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * The current entity manager.
     *
     * @var EntityManager
     */
    private $em;

    /**
     * The current BackBee application.
     *
     * @var BBApplication
     */
    private $bbapp;

    /**
     * ACL provider object.
     *
     * @var MutableAclProvider
     */
    private $aclProvider;

    /**
     * Users/groups/rights tables
     * @var array
     */
    private $tables = [
        'user_group',
        'group',
        'user',
        'acl_classes',
        'acl_entries',
        'acl_object_identities',
        'acl_object_identity_ancestors',
        'acl_security_identities'
    ];

    /**
     * Class content manager
     * @var \BackBee\ClassContent\ClassContentManager
     */
    private $classContentManager;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('users:update_rights')
            ->setDescription('Update users rights from a yaml file')
            ->addOption('clean', null, InputOption::VALUE_OPTIONAL, 'Cleaning all tables including group and user, <comment>to be used with caution</comment>', false)
            ->addOption('memory-limit', 'm', InputOption::VALUE_OPTIONAL, 'The memory limit to set.')
            ->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'The yaml file to be parsed, <comment>[default: %config_dir%/groups.yml]</comment>')
            ->setHelp(<<<EOF
The <info>%command.name%</info> updates users rights from a yaml file. The supported syntax for yaml file is:
    <info>group_name</info>:
        <info>description</info>: Description of the group of users
        # Rights for Site instances
        <info>sites</info>:
            <info>resources</info>: (all|[sites uids and/or sites labels])
            <info>actions</info>: (all|[none or several from view, create, edit, publish, delete])
        # Rights for Layout instances
        <info>layouts</info>:
            <info>resources</info>: (all|[layouts uids and/or layouts labels])
            <info>actions</info>: (all|[none or several from view, create, edit, publish, delete])
        # Rights for Workflow instances
        <info>workflow</info>:
            <info>resources</info>: (all|[workflow uids and/or workflow labels])
            <info>actions</info>: (all|[none or several from view, create, edit, publish, delete])
        # Rights for MediaFolder instances
        <info>mediafolders</info>:
            <info>resources</info>: (all|[folders uids and/or folders urls])
            <info>actions</info>: (all|[none or several from view, create, edit, publish, delete])
        # Rights for Page instances
        <info>pages</info>:
            <info>resources</info>: (all|[pages uids and/or pages urls])
            <info>actions</info>: (all|[none or several from view, create, edit, publish, delete])
        # Rights for AbstractClassContent instances
        <info>contents</info>:
            <info>resources</info>: (all|[sites uids and/or sites label])
            <info>actions</info>: (all|[none or several from view, create, edit, publish, delete])
        # Rights for AbstractBundle instances
        <info>bundles</info>:
            <info>resources</info>: (all|[bundle service ids])
            <info>actions</info>: (all|[none or several from view, create, edit, publish, delete])
        # Rights for User instances
        <info>users</info>:
            <info>resources</info>: (all|[user uids])
            <info>actions</info>: (all|[none or several from view, create, edit, publish, delete])
        # Rights for Group instances
        <info>groups</info>:
            <info>resources</info>: (all|[group uids])
            <info>actions</info>: (all|[none or several from view, create, edit, publish, delete])
EOF
            )
        ;
    }

    /**
     * Initializes the command just after the input has been validated.
     *
     * This is mainly useful when a lot of commands extends one main command
     * where some things need to be initialized based on the input arguments and options.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->output = $output;

        $this->bbapp = $this->getContainer()->get('bbapp');
        $this->em = $this->bbapp->getEntityManager();
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);

        $this->aclProvider = $this->bbapp
                                  ->getSecurityContext()
                                  ->getACLProvider();

        $this->classContentManager = $this->bbapp
                                          ->getContainer()
                                          ->get('classcontent.manager')
                                          ->setBBUserToken($this->bbapp->getBBUserToken());

        if (null !== $input->getOption('memory-limit')) {
            ini_set('memory_limit', $input->getOption('memory-limit'));
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $startingTime = microtime(true);

        try {
            $config = $this->getGroupsConfig($input->getOption('file'));

            $this->checksAclProvider()
                ->checksBackBeeVersion()
                ->checksUserTable()
                ->checksGroupTable()
                ->checkAclTables()
                ->cleanTables($input)
                ->checksGroups($config);

            $this->writeln(sprintf('<info>Update done in %d s.</info>', microtime(true) - $startingTime));

            return 0;
        } catch (\Exception $ex) {
            $this->writeln('');
            $this->writeln(sprintf('<error>    Error: %s    </error>', $ex->getMessage()));
            $this->writeln('');
        }

        return -1;
    }

    /**
     * Reads the user rights config from $filename if provided, elsewhere Application config.
     *
     * @param  string|null $filename     The yaml file to be parsed.
     *
     * @return array                     The parsed configuration.
     *
     * @throws \InvalidArgumentException if something went wrong.
     */
    private function getGroupsConfig($filename = null)
    {
        $config = $this->bbapp->getConfig()->getGroupsConfig();
        if (null !== $filename) {
            if (!is_readable($filename)) {
                throw new \InvalidArgumentException(sprintf('Cannot read file %s', $filename));
            }

            $config = Yaml::parse(file_get_contents($filename));
        }

        if (false === is_array($config)) {
            throw new \InvalidArgumentException('Malformed groups.yml file, aborting');
        }

        return $config;
    }

    /**
     * Checks for a valid ACL provider.
     *
     * @return UpdateUsersRightsCommand
     *
     * @throws \InvalidArgumentException if thee ACL provider is not valid.
     */
    private function checksAclProvider()
    {
        if (null === $this->aclProvider) {
            throw new \InvalidArgumentException('None ACL provider found');
        }

        return $this;
    }

    /**
     * Checks for BackBee version, at least 1.1.0 is required
     *
     * @return UpdateUsersRightsCommand
     */
    private function checksBackBeeVersion()
    {
        $this->writeln('<info>Checking BackBee instance</info>', OutputInterface::VERBOSITY_VERBOSE);
        $this->write(sprintf(' - BackBee version: %s - ', BBApplication::VERSION), false, OutputInterface::VERBOSITY_VERY_VERBOSE);

        if (0 > version_compare(BBApplication::VERSION, '1.1')) {
            throw new \RuntimeError(sprintf('This command needs at least BackBee v1.1.0 installed, gets BackBee v%s.%sPlease upgrade your distribution.', BBApplication::VERSION, PHP_EOL));
        }

        $this->writeln('<info>OK</info>', OutputInterface::VERBOSITY_VERY_VERBOSE);

        return $this;
    }

    /**
     * Checks for existing table `user`
     *
     * @return UpdateUsersRightsCommand
     *
     * @throws \RuntimeException
     */
    private function checksUserTable()
    {
        return $this->checksTable(User::class, [
            'id',
            'login',
            'email',
            'password',
            'state',
            'activated',
            'firstname',
            'lastname',
            'api_key_public',
            'api_key_private',
            'api_key_enabled',
            'created',
            'modified',
        ]);
    }

    /**
     * Checks for existing table `group`
     *
     * @return  UpdateUsersRightsCommand
     *
     * @throws \RuntimeException
     */
    private function checksGroupTable()
    {
        return $this->checksTable(Group::class, [
            'id',
            'name',
            'description',
            '*site_uid'
        ]);
    }

    /**
     * Checks ACL tables, creates them if they don't exit.
     *
     * @return UpdateUsersRightsCommand
     */
    private function checkAclTables()
    {
        $this->writeln('<info>Checking ACL tables</info>', OutputInterface::VERBOSITY_VERBOSE);
        $this->write(' - ACL tables - ', false, OutputInterface::VERBOSITY_VERY_VERBOSE);

        $schemaManager = $this->em->getConnection()->getSchemaManager();

        $dropTableSql = [];
        $tablesMapping = [
            'class_table_name'         => 'acl_classes',
            'entry_table_name'         => 'acl_entries',
            'oid_table_name'           => 'acl_object_identities',
            'oid_ancestors_table_name' => 'acl_object_identity_ancestors',
            'sid_table_name'           => 'acl_security_identities',
        ];

        foreach ($tablesMapping as $value) {
            if ($schemaManager->tablesExist(array($value)) === true) {
                $dropTableSql[] = 'DROP TABLE IF EXISTS `' . $value. '`;';
            }
        }

        if (count($dropTableSql) != count($tablesMapping)) {
            if (!empty($dropTableSql)) {
                $this->em->getConnection()->executeUpdate('SET FOREIGN_KEY_CHECKS=0');
                $this->em->getConnection()->executeQuery(implode(' ', $dropTableSql));
                $this->em->getConnection()->executeUpdate('SET FOREIGN_KEY_CHECKS=1');
            }

            $schema = new Schema($tablesMapping);
            $platform = $this->em->getConnection()->getDatabasePlatform();

            foreach ($schema->toSql($platform) as $query) {
                $this->em->getConnection()->executeQuery($query);
            }

            $this->writeln('<info>created</info>', OutputInterface::VERBOSITY_VERY_VERBOSE);
        } else {
            $this->writeln('<info>OK</info>', OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        return $this;
    }

    /**
     * Checks for an existing table for entity.
     *
     * @param  string $classname
     * @param  array  $fields
     *
     * @return UpdateUsersRightsCommand
     *
     * @throws \RuntimeException
     */
    private function checksTable($classname, array $fields)
    {
        $schemaManager = $this->em->getConnection()->getSchemaManager();
        $metadata = $this->em->getClassMetadata($classname);
        $tableName = $metadata->getTableName();

        $this->writeln(sprintf('<info>Checking %s table</info>', $tableName), OutputInterface::VERBOSITY_VERBOSE);
        $this->write(sprintf(' - Existing table `%s` - ', $tableName), false, OutputInterface::VERBOSITY_VERY_VERBOSE);

        $requiredFields = [];
        foreach ($fields as $field) {
            if ('*' === substr($field, 0, 1)) {
                $columnName = $metadata->getSingleAssociationJoinColumnName(str_replace(['*', '_uid'], ['_', ''], $field));
            } else {
                $columnName = $metadata->getColumnName('_' . $field);
            }

            $requiredFields[$field] = $columnName;
        }

        $existingFields = array_keys($schemaManager->listTableColumns($tableName));
        $missingFields = array_diff($requiredFields, $existingFields);

        if (1 < count($missingFields)) {
            throw new \RuntimeException(sprintf('The table `%s` exists but is not up-to-date, please launch `bbapp:update` command.', $tableName));
        }

        $this->writeln('<info>OK</info>', OutputInterface::VERBOSITY_VERY_VERBOSE);

        return $this;
    }

    /**
     * Cleans current users and rights if asked.
     *
     * @param  InputInterface $input
     *
     * @return UpdateUsersRightsCommand
     *
     * @throws \InvalidArgumentException
     */
    private function cleanTables(InputInterface $input)
    {
        $aclOnly = true;
        if ($input->getOption('clean')) {
            $this->writeln('<info>Deleting current users and groups.</info>', OutputInterface::VERBOSITY_VERBOSE);

            if (OutputInterface::VERBOSITY_QUIET < $this->output->getVerbosity()) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion('This will delete all existing users and groups, continue?', false, '/^(y)/i');

                if (!$helper->ask($input, $this->output, $question)) {
                    throw new \InvalidArgumentException('Aborted');
                }
            }

            $aclOnly = false;
        }

        $this->em->getConnection()->executeUpdate('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->tables as $table) {
            if ($aclOnly && 'acl' !== substr($table, 0, 3)) {
                continue;
            }

            $this->em->getConnection()->executeQuery(sprintf('TRUNCATE `%s`', $table));
            $this->writeln(sprintf(' - Table `%s` truncated - <info>OK</info>', $table), OutputInterface::VERBOSITY_VERY_VERBOSE);
        }
        $this->em->getConnection()->executeUpdate('SET FOREIGN_KEY_CHECKS=1');

        return $this;
    }

    /**
     * Checks for groups, creates them if they don't exist.
     *
     * @param  array $config
     *
     * @return UpdateUsersRightsCommand
     */
    public function checksGroups(array $config)
    {
        $this->writeln('<info>Updating groups and their rights.</info>', OutputInterface::VERBOSITY_VERBOSE);

        foreach ($config as $identifier => $rights) {
            $group = $this->em->getRepository(Group::class)->findOneBy(['_name' => $identifier]);
            if (null === $group) {
                $group = new Group();
                $group->setName($identifier)
                    ->setDescription(Collection::get($rights, 'description', $identifier));
                $this->em->persist($group);
                $this->em->flush($group);
            }

            $this->writeln(sprintf(' - Group `%s`', $identifier), OutputInterface::VERBOSITY_VERY_VERBOSE);
            $this->updateRights($group, $rights);
        }

        return $this;
    }

    /**
     * Updates rights associated to a group
     *
     * @param Group $group
     * @param array $config
     */
    private function updateRights(Group $group, array $config)
    {
        $securityIdentity = new UserSecurityIdentity($group->getObjectIdentifier(), Group::class);

        foreach ($config as $object => $rights) {
            $methodName = sprintf('add%sRights', ucfirst($object));
            if (method_exists($this, $methodName)) {
                call_user_func_array([$this, $methodName], [$rights, $securityIdentity]);
            }
        }
    }

    /**
     * Adding rights on generic objects.
     *
     * @param string               $classname
     * @param array                $config
     * @param UserSecurityIdentity $securityIdentity
     * @param string               $labelField
     */
    private function addObjectRights($classname, array $config, UserSecurityIdentity $securityIdentity, $labelField = '_label')
    {
        $rights = $this->getActionsOnResources($config);
        foreach ((array) $rights as $resource => $actions) {
            if ('all' === $resource) {
                $this->addClassAcl($classname, $securityIdentity, $actions);
                $this->writeln(sprintf('    - Setting [%s] on all %s', implode(', ', $actions), $classname), OutputInterface::VERBOSITY_DEBUG);
                continue;
            }

            $object = $this->em->getRepository($classname)->findOneBy([$labelField => $resource]);
            if (null === $object) {
                $object = $this->em->find($classname, $resource);
            }

            if (null === $object) {
                $this->writeln(sprintf('    - Unknown %s `%s`', $classname, $resource), OutputInterface::VERBOSITY_DEBUG);
                continue;
            }

            $this->addObjectAcl($object, $securityIdentity, $actions);
            $this->writeln(sprintf('    - Setting [%s] on all %s `%s`', implode(', ', $actions), $classname, $resource), OutputInterface::VERBOSITY_DEBUG);
        }
    }

    /**
     * Adding rights on Site objects.
     *
     * @param array                $config
     * @param UserSecurityIdentity $securityIdentity
     */
    private function addSitesRights(array $config, UserSecurityIdentity $securityIdentity)
    {
        $this->addObjectRights(Site::class, $config, $securityIdentity);
    }

    /**
     * Adding rights on Layout objects.
     *
     * @param array                $config
     * @param UserSecurityIdentity $securityIdentity
     */
    private function addLayoutsRights(array $config, UserSecurityIdentity $securityIdentity)
    {
        $this->addObjectRights(Layout::class, $config, $securityIdentity);
    }

    /**
     * Adding rights on Page objects.
     *
     * @param array                $config
     * @param UserSecurityIdentity $securityIdentity
     */
    private function addPagesRights(array $config, UserSecurityIdentity $securityIdentity)
    {
        $this->addObjectRights(Page::class, $config, $securityIdentity, '_url');
    }

    /**
     * Adding rights on MediaFolder objects.
     *
     * @param array                $config
     * @param UserSecurityIdentity $securityIdentity
     */
    private function addMediafoldersRights(array $config, UserSecurityIdentity $securityIdentity)
    {
        $this->addObjectRights(MediaFolder::class, $config, $securityIdentity, '_url');
    }

    /**
     * Adding rights on Group objects.
     *
     * @param array                $config
     * @param UserSecurityIdentity $securityIdentity
     */
    private function addGroupsRights(array $config, UserSecurityIdentity $securityIdentity)
    {
        $this->addObjectRights(Group::class, $config, $securityIdentity, '_name');
    }

    /**
     * Adding rights on User objects.
     *
     * @param array                $config
     * @param UserSecurityIdentity $securityIdentity
     */
    private function addUsersRights(array $config, UserSecurityIdentity $securityIdentity)
    {
        $this->addObjectRights(User::class, $config, $securityIdentity, '_username');
    }

    /**
     * Adding rights on AbstractBundle objects.
     *
     * @param  array $config
     * @param  UserSecurityIdentity $securityIdentity
     */
    private function addBundlesRights(array $config, UserSecurityIdentity $securityIdentity)
    {
        $rights = $this->getActionsOnResources($config);
        foreach ((array) $rights as $resource => $actions) {
            if ('all' === $resource) {
                foreach ($this->bbapp->getBundles() as $bundle) {
                    $this->addObjectAcl($bundle, $securityIdentity, $actions);
                }
                $this->writeln(sprintf('    - Setting [%s] on all bundles', implode(', ', $actions)), OutputInterface::VERBOSITY_DEBUG);
                continue;
            }

            if (null === $bundle = $this->bbapp->getBundle($resource)) {
                $this->writeln(sprintf('    - Unknown bundle `%s`', $resource), OutputInterface::VERBOSITY_DEBUG);
                continue;
            }

            $this->addObjectAcl($bundle, $securityIdentity, $actions);
            $this->writeln(sprintf('    - Setting [%s] on all bundle `%s`', implode(', ', $actions), $resource), OutputInterface::VERBOSITY_DEBUG);
        }
    }

    /**
     * Adding rights on AbstractClassContent objects.
     *
     * @param  array $config
     * @param  UserSecurityIdentity $securityIdentity
     */
    private function addContentsRights(array $config, UserSecurityIdentity $securityIdentity)
    {
        $rights = $this->getActionsOnResources($config);
        foreach ((array) $rights as $resource => $actions) {
            if ('all' === $resource) {
                $this->addClassAcl(AbstractClassContent::class, $securityIdentity, $actions);
                $this->writeln(sprintf('    - Setting [%s] on all content', implode(', ', $actions)), OutputInterface::VERBOSITY_DEBUG);
                continue;
            }

            try {
                $classname = AbstractClassContent::getFullClassname($resource);
                if (class_exists($classname)) {
                    $this->addClassAcl($classname, $securityIdentity, $actions);
                    $this->writeln(sprintf('    - Setting [%s] on %s', implode(', ', $actions), $cclassname), OutputInterface::VERBOSITY_DEBUG);
                }
            } catch (\Exception $ex) {
                $this->writeln(sprintf('    - Unknown content `%s`', $classname), OutputInterface::VERBOSITY_DEBUG);
            }
        }
    }

    /**
     * Format actions on resources.
     *
     * @param  array $config
     *
     * @return array
     */
    private function getActionsOnResources($config)
    {
        $result = [];

        $resources = (array) Collection::get($config, 'resources', []);
        $actions = (array) Collection::get($config, 'actions', []);
        if (!isset($actions[0]) || !is_array($actions[0])) {
            $actions = [$actions];
        }

        $index = 0;
        foreach ($resources as $resource) {
            if ('!' === substr($resource, 0, 1)) {
                $resource = substr($resource, 1);
                $result[$resource] = [];
                continue;
            }

            $action = isset($actions[$index]) ? $actions[$index] : $actions[0];
            $result[$resource] = $this->getActions($action);
        }

        return $result;
    }

    /**
     * Filters the actions.
     *
     * @param  mixed $def
     *
     * @return array
     */
    private function getActions($def)
    {
        $all = ['view', 'create', 'edit', 'delete', 'publish'];
        if (['all'] === $def) {
            return $all;
        }

        return array_intersect($all, $def);
    }

    /**
     * Add rights to security group on whole instances of class.
     *
     * @param string               $className
     * @param UserSecurityIdentity $securityIdentity
     * @param array                $rights
     */
    private function addClassAcl($className, UserSecurityIdentity $securityIdentity, array $rights)
    {
        $objectIdentity = new ObjectIdentity('all', $className);
        $this->addAcl($objectIdentity, $securityIdentity, $rights);
    }

    /**
     * Add rights to security group on one instance of object.
     *
     * @param object               $object
     * @param UserSecurityIdentity $securityIdentity
     * @param array                $rights
     */
    private function addObjectAcl($object, UserSecurityIdentity $securityIdentity, array $rights)
    {
        $objectIdentity = ObjectIdentity::fromDomainObject($object);
        $this->addAcl($objectIdentity, $securityIdentity, $rights);
    }

    /**
     * Add rights to security group on object.
     *
     * @param ObjectIdentity       $objectIdentity
     * @param UserSecurityIdentity $securityIdentity
     * @param array                $rights
     */
    private function addAcl(ObjectIdentity $objectIdentity, UserSecurityIdentity $securityIdentity, array $rights)
    {
        // Getting ACL for this object identity
        try {
            $acl = $this->aclProvider->createAcl($objectIdentity);
        } catch (\Exception $e) {
            $acl = $this->aclProvider->findAcl($objectIdentity);
        }

        // Calculating mask
        $builder = new MaskBuilder();
        foreach ($rights as $right) {
            $builder->add($right);
        }
        $mask = $builder->get();

        // first revoke existing access for this security identity
        foreach($acl->getObjectAces() as $i => $ace) {
            if($securityIdentity->equals($ace->getSecurityIdentity())) {
                $acl->updateObjectAce($i, $ace->getMask() & ~$mask);
            }
        }

        // then grant
        if ('all' === $objectIdentity->getIdentifier()) {
            $acl->insertClassAce($securityIdentity, $mask);
        } else {
            $acl->insertObjectAce($securityIdentity, $mask);
        }
        $this->aclProvider->updateAcl($acl);
    }

    /**
     * Writes a message to the output and adds a newline at the end.
     *
     * @param string|array $messages  The message as an array of lines or a single string
     * @param int          $verbosity The verbosity of output (one of the VERBOSITY constants)
     */
    protected function writeln($messages, $verbosity = OutputInterface::VERBOSITY_NORMAL)
    {
        $this->write($messages, true, $verbosity);
    }

    /**
     * Writes a message to the output
     *
     * @param string|array $messages  The message as an array of lines or a single string
     * @param bool         $newLine   Whether to add a newline
     * @param int          $verbosity The verbosity of output (one of the VERBOSITY constants)
     */
    protected function write($messages, $newLine = false, $verbosity = OutputInterface::VERBOSITY_NORMAL)
    {
        if ($verbosity <= $this->output->getVerbosity()) {
            $this->output->write($messages);

            if (true === $newLine) {
                $this->output->writeln('');
            }
        }
    }
}