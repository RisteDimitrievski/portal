<?php

declare(strict_types=1);

namespace Doctrine\ORM\Tools\Console\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\MetadataFilter;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function file_exists;
use function is_dir;
use function is_writable;
use function mkdir;
use function realpath;
use function sprintf;

/**
 * Command to (re)generate the proxy classes used by doctrine.
 */
class GenerateProxiesCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('orm:generate-proxies')
             ->setAliases(['orm:generate:proxies'])
             ->setDescription('Generates proxy classes for entity classes')
             ->addArgument('dest-path', InputArgument::OPTIONAL, 'The path to generate your proxy classes. If none is provided, it will attempt to grab from configuration.')
             ->addOption('filter', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A string pattern used to match entities that should be processed.')
             ->setHelp('Generates proxy classes for entity classes.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ui = new SymfonyStyle($input, $output);

        /** @var EntityManagerInterface $em */
        $em = $this->getHelper('em')->getEntityManager();

        $metadatas = $em->getMetadataFactory()->getAllMetadata();
        $metadatas = MetadataFilter::filter($metadatas, $input->getOption('filter'));
        $destPath  = $input->getArgument('dest-path');

        // Process destination directory
        if ($destPath === null) {
            $destPath = $em->getConfiguration()->getProxyDir();
        }

        if (! is_dir($destPath)) {
            mkdir($destPath, 0775, true);
        }

        $destPath = realpath($destPath);

        if (! file_exists($destPath)) {
            throw new InvalidArgumentException(
                sprintf("Proxies destination directory '<info>%s</info>' does not exist.", $em->getConfiguration()->getProxyDir())
            );
        }

        if (! is_writable($destPath)) {
            throw new InvalidArgumentException(
                sprintf("Proxies destination directory '<info>%s</info>' does not have write permissions.", $destPath)
            );
        }

        if (empty($metadatas)) {
            $ui->success('No Metadata Classes to process.');

            return;
        }

        foreach ($metadatas as $metadata) {
            $ui->text(sprintf('Processing entity "<info>%s</info>"', $metadata->getClassName()));
        }

        // Generating Proxies
        $em->getProxyFactory()->generateProxyClasses($metadatas, $destPath);

        // Outputting information message
        $ui->newLine();
        $ui->text(sprintf('Proxy classes generated to "<info>%s</info>"', $destPath));
    }
}
