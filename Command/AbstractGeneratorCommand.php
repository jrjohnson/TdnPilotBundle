<?php

namespace Tdn\PilotBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\HttpKernel\KernelInterface;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Mapping\ClassMetadata;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Tdn\PilotBundle\Manipulator\ManipulatorInterface;
use Tdn\PilotBundle\Manipulator\ServiceManipulatorInterface;
use Tdn\PilotBundle\Model\File;
use Tdn\PilotBundle\Model\Format;
use Tdn\PilotBundle\Services\Utils\Doctrine\EntityUtils;
use Tdn\PilotBundle\Template\Strategy\TemplateStrategyUtils;
use Tdn\PilotBundle\Template\Strategy\TemplateStrategyInterface;
use Tdn\PilotBundle\Services\Utils\Symfony\ServiceFileUtils;

/**
 * Class AbstractGeneratorCommand
 *
 * Basic class that all generator commands can extend.
 *
 * @package Tdn\PilotBundle\Command
 */
abstract class AbstractGeneratorCommand extends ContainerAwareCommand
{
    /**
     * Override in child class
     * @var string
     */
    const NAME = '';

    /**
     * Override in child class
     * @var string
     */
    const DESCRIPTION = '';

    /**
     * @var string
     */
    const DEFAULT_FORMAT = Format::YAML;

    /**
     * @var string
     */
    private $entity;

    /**
     * @var EntityUtils
     */
    private $entityUtils;

    /**
     * @var ManipulatorInterface
     */
    private $manipulator;

    /**
     * @return string[]
     */
    abstract protected function getFiles();

    /**
     * @return ManipulatorInterface
     */
    abstract protected function createManipulator();

    /**
     * @param ManipulatorInterface $manipulator
     */
    public function setManipulator(ManipulatorInterface $manipulator)
    {
        $this->manipulator = $manipulator;
    }

    /**
     * Returns current manipulator if set, otherwise returns a new instance.
     *
     * Returns the expected manipulator (based on createManipulator) for the current entity.
     * Does not assign $this->manipulator so that a new instance is created every time.
     *
     * @param TemplateStrategyInterface $templateStrategy
     * @param BundleInterface           $bundle
     * @param ClassMetadata             $metadata
     * @param string                    $format
     * @param bool                      $overWrite
     * @param string                    $targetDirectory
     *
     * @return ManipulatorInterface
     */
    public function getManipulator(
        TemplateStrategyInterface $templateStrategy,
        BundleInterface $bundle,
        ClassMetadata $metadata,
        $format = Format::YML,
        $overWrite = false,
        $targetDirectory = null
    ) {
        if ($this->manipulator) {
            return $this->manipulator;
        } else {
            $manipulator = $this->createManipulator();

            $manipulator->setMetadata($metadata);
            $manipulator->setTemplateStrategy($templateStrategy);
            $manipulator->setBundle($bundle);

            if ($overWrite) {
                $manipulator->setOverwrite($overWrite);
            }

            if ($targetDirectory) {
                $manipulator->setTargetDirectory($targetDirectory);
            }

            if ($format) {
                $manipulator->setFormat($format);
            }

            if ($manipulator instanceof ServiceManipulatorInterface) {
                $manipulator->setServiceFileUtils($this->getServiceFileUtils());
                //Maybe add an abstract service generator command that adds this option
            }

            return $manipulator;
        }
    }

    /**
     * @param string $entity
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;
    }

    /**
     * @return string
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @param EntityUtils $entityUtils
     */
    public function setEntityUtils(EntityUtils $entityUtils)
    {
        $this->entityUtils = $entityUtils;
    }

    /**
     * @return EntityUtils
     */
    public function getEntityUtils()
    {
        if (null === $this->entityUtils) {
            $this->setEntityUtils(new EntityUtils());
        }

        return $this->entityUtils;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        if (static::NAME == '' || static::DESCRIPTION == '') {
            throw new \LogicException(
                'Please set the name and description of the command. Error in: ' . get_called_class()
            );
        }

        $this
            ->addOption(
                'entity',
                't',
                InputOption::VALUE_OPTIONAL,
                'The entity class name to initialize (shortcut notation: FooBarBundle:Entity)'
            )
            ->addOption(
                'entities-location',
                'l',
                InputOption::VALUE_OPTIONAL,
                'The directory containing the entity classes to target'
            )
            ->addOption(
                'overwrite',
                'o',
                InputOption::VALUE_NONE,
                'Overwrite existing ' . implode(',', $this->getFiles())
            )
            ->addOption(
                'target-directory',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Specify a different target directory (namespaces will have to be changed manually)'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'The service file format (yaml, xml, annotations). default: yaml',
                self::DEFAULT_FORMAT
            )
            ->addOption(
                'exclude',
                'x',
                (InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY),
                'File-names to exclude when generating files (with --entities-location). ' .
                'No ext required. Case insensitive',
                []
            )
            ->setDescription(static::DESCRIPTION)
            ->setName(static::NAME)
        ;
    }

    /**
     * Generates the files based on the options provided.
     *
     * Sets up all dependencies for manipulator, prepares it
     * and ultimately tells it to generate it's files.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (($input->getOption('entity') === null && $input->getOption('entities-location') === null) ||
            ($input->getOption('entity') !== null && $input->getOption('entities-location') !== null)
        ) {
            $output->writeln('<error>Please use either entity OR entities-location. One is required.</error>');

            return 1;
        }

        $entities = $this->getEntityUtils()->getEntityDirAsCollection(
            $input->getOption('entities-location'),
            $input->getOption('exclude')
        );
        $doctrine = $this->getManagerRegistry();
        $templateStrategy = $this->getTemplateStrategy();

        if (null !== $entity = $input->getOption('entity')) {
            $entities->add($entity);
        }

        $output->writeln(
            sprintf(
                'Generating files for %s entit%s... (You can skip interaction by passing in the %s flag)',
                $entities->count(),
                $entities->count() > 1 ? 'ies' : 'y',
                '--no-interaction'
            )
        );

        foreach ($entities as $entity) {
            $entity = Validators::validateEntityName($entity);
            list($bundle, $entity) = $this->getEntityUtils()->parseShortcutNotation($entity);
            $this->setEntity($doctrine->getAliasNamespace($bundle) . '\\' . $entity);
            //This could be made better if we could guarantee that all entities share the same bundle
            //(although they should)
            $bundle = $this->getKernel()->getBundle($bundle);
            $templateStrategy->setSkeletonDirs(
                $this->getTemplateStrategyUtils()->getDefaultSkeletonDirs($bundle)
            );

            try {
                //Same as above, and just calling setMetadata() instead...hmm.
                $manipulator = $this->getManipulator(
                    $templateStrategy,
                    $bundle,
                    $this->getEntityUtils()->getMetadata($doctrine, $this->getEntity()),
                    $input->getOption('format'),
                    ($input->getOption('overwrite') ? true : false),
                    ($input->hasOption('target-directory') ? $input->getOption('target-directory') : null)
                )->prepare();

                if ($this->shouldContinue($input, $output, $manipulator->getFiles(), $entity)) {
                    $output->write(PHP_EOL);
                    foreach ($manipulator->generate() as $file) {
                        $this->printFileGeneratedMessage($output, $file);
                    }
                    $output->write(PHP_EOL);
                    foreach ($manipulator->getMessages() as $message) {
                        $output->writeln(
                            sprintf(
                                '<comment>%s</comment>',
                                $message
                            )
                        );
                    }
                    $output->write(PHP_EOL);
                } else {
                    $output->writeln('<error>Generation cancelled.</error>');

                    return 1;
                }
            } catch (\Exception $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return 1;
            }
        }

        return 0;
    }

    /**
     * @return KernelInterface
     */
    protected function getKernel()
    {
        return $this->getContainer()->get('kernel');
    }

    /**
     * @return ManagerRegistry
     */
    protected function getManagerRegistry()
    {
        return $this->getContainer()->get('doctrine');
    }

    /**
     * @return TemplateStrategyInterface
     */
    protected function getTemplateStrategy()
    {
        return $this->getContainer()->get('tdn_pilot.template.strategy.default');
    }

    /**
     * @return ServiceFileUtils
     */
    protected function getServiceFileUtils()
    {
        return $this->getContainer()->get('tdn_pilot.symfony.service.utils');
    }

    /**
     * @return QuestionHelper
     */
    protected function getQuestionHelper()
    {
        return $this->getHelperSet()->get('question');
    }

    /**
     * Override in sub type to add more arguments or options to input.
     * @return array
     */
    protected function getInputArgs()
    {
        return [];
    }

    /**
     * @param OutputInterface $output
     * @param File $file
     */
    protected function printFileGeneratedMessage(OutputInterface $output, File $file)
    {
        $output->writeln(sprintf(
            'The new <info>%s</info> file has been created under <info>%s</info>.',
            $file->getFilename(),
            $file->getRealPath()
        ));

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln(sprintf(
                'Contents:' . PHP_EOL . '%s',
                $file->getFilteredContents()
            ));
        }
    }

    /**
     * Confirms generation
     *
     * If input is interactive asks to confirm generation of files. You have to explicitly
     * confirm to continue.
     * If input is **not** interactive, then it will automatically return true.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param ArrayCollection $generatedFiles
     * @param string          $entity
     *
     * @return bool
     */
    protected function shouldContinue(
        InputInterface $input,
        OutputInterface $output,
        ArrayCollection $generatedFiles,
        $entity
    ) {
        if ($input->isInteractive()) {
            $question = new ConfirmationQuestion(
                sprintf(
                    'Entity %s - File(s) to be modified:' .
                    PHP_EOL . '<info>%s</info>' .
                    PHP_EOL . 'Do you confirm generation/manipulation of the files listed above (y/n)?',
                    $entity,
                    implode(PHP_EOL, $generatedFiles->map(function (File $generatedFile) {
                        return '- ' . $generatedFile->getRealPath();
                    })->toArray())
                ),
                false
            );

            return (bool) $this->getQuestionHelper()->ask($input, $output, $question);
        }

        return true;
    }

    /**
     * @return TemplateStrategyUtils
     */
    protected function getTemplateStrategyUtils()
    {
        return new TemplateStrategyUtils();
    }
}
