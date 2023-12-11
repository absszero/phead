<?php
namespace Absszero\Head;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Console\Output\OutputInterface;

class HeadCommand extends Command
{
    protected Layout $layout;

    protected function configure()
    {
        $this
        ->setName('head')
        ->setDescription('Generate code by layout')
        ->addArgument('layout', InputArgument::REQUIRED, 'The layout to use.')
        ->addOption('dry', 'd', InputOption::VALUE_NONE, 'Dry run.')
        ->addOption('overwrite', 'o', InputOption::VALUE_NONE, 'Overwrite existed files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $file = $input->getArgument('layout');
            $this->layout = Layout::parse($file);
        } catch (ParseException $th) {
            $output->writeln('<error>'. $th->getMessage() .'</error>');
            return 1;
        }

        $cwd = getcwd();
        $output->writeln('<info>Generating files...</info>');
        $files = $this->layout->get('files');
        foreach ($files as $index => $file) {
            if (is_file($file['from']) and is_readable($file['from'])) {
                $file['from'] = file_get_contents($file['from']);
            }

            $file['from'] = $this->layout->replacePlaceholders($file['from'], $file);
            $file['from'] = $this->layout->appendMethods($file);
            $file['to_path'] = $cwd . '/' . $file['to'];

            $files[$index] = $file;

            $output->writeln('<info>' . $file['to'] . '</info>');
            if ($input->getOption('dry')) {
                continue;
            }

            if (file_exists($file['to']) && !$input->getOption('overwrite')) {
                continue;
            }

            $dir = dirname($file['to_path']);
            !is_dir($dir) && mkdir($dir, 0777, true);
            file_put_contents($file['to_path'], $file['from']);
        }

        return 0;
    }
}
