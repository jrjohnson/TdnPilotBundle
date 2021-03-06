<?php

namespace Tdn\PilotBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Tdn\PilotBundle\Manipulator\RoutingManipulator;

/**
 * Class GenerateRoutingCommand
 *
 * Adds / Removes routes from the routing file based on an entity.
 *
 * @package Tdn\SfRoutingGeneratorBundle\Command
 */
class GenerateRoutingCommand extends AbstractGeneratorCommand
{
    /**
     * @var string
     */
    const DEFAULT_ROUTING = 'routing';

    /**
     * @var string
     */
    const NAME = 'tdn:generate:routing';

    /**
     * @var string
     */
    const DESCRIPTION =
        'Adds a routing entry for a rest controller based on an entity. Removes it with the --remove flag.';

    /**
     * @var string
     */
    protected $routingFile;

    /**
     * @var string
     */
    protected $routePrefix;

    /**
     * @var bool
     */
    protected $remove;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->addArgument(
                'routing-file',
                InputArgument::OPTIONAL,
                'The routing file, defaults to: ' . self::DEFAULT_ROUTING . '.' . self::DEFAULT_FORMAT,
                self::DEFAULT_ROUTING . '.' . self::DEFAULT_FORMAT
            )
            ->addOption(
                'route-prefix',
                '',
                InputOption::VALUE_REQUIRED,
                'The route prefix'
            )
            ->addOption(
                'remove',
                '',
                InputOption::VALUE_NONE,
                'Remove route instead of add.'
            )
        ;

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->routingFile = $input->getArgument('routing-file');
        $this->routePrefix = $input->getOption('route-prefix');
        $this->remove      = $input->getOption('remove') ? true : false;

        return parent::execute($input, $output);
    }

    /**
     * @return RoutingManipulator
     */
    protected function createManipulator()
    {
        $manipulator = new RoutingManipulator();
        $manipulator->setRoutingFile($this->routingFile);
        $manipulator->setRoutePrefix($this->routePrefix);
        $manipulator->setRemove($this->remove);

        return $manipulator;
    }

    /**
     * @return string[]
     */
    protected function getFiles()
    {
        return ['Routing config'];
    }
}
