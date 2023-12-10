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
    protected $dry;

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
        $this->dry = $input->getOption('dry');

        try {
            $file = $input->getArgument('layout');
            $layout = Layout::parse($file);
        } catch (ParseException $th) {
            $output->writeln('<error>'. $th->getMessage() .'</error>');
            return 1;
        }

        $files = [];
        foreach ($layout->files as $file) {
            if (!array_key_exists('from', $file)) {
                continue;
            }
            if (!array_key_exists('to', $file)) {
                continue;
            }
            $files[] = $file;
        }

        $cwd = getcwd();

        $output->writeln('<info>Generating files...</info>');
        foreach ($files as $file) {
            if (is_file($file['from']) and is_readable($file['from'])) {
                $file['from'] = file_get_contents($file['from']);
            }

            $file['from'] = $this->replacePlaceholders($file);
            $file['to'] = $cwd . '/' . $file['to'];
            $output->writeln('<info>' . $file['to'] . '</info>');

            if ($this->dry) {
                continue;
            }
            $dir = dirname($file['to']);
            mkdir($dir, 0777, true);
            file_put_contents($file['to'], $file['from']);
        }

        return 0;
    }

    protected function replacePlaceholders(array $file):  string
    {
        $search = [
            '{{ class }}',
            '{{ namespace }}',
        ];
        $replace = [];

        $classFile = basename($file['to']);
        $replace[] = ucfirst(strstr($classFile, '.', true));

        // make a PSR-4 namespace
        $namespace = strstr($file['to'], $classFile, true);
        $namespace = str_replace('/', '\\', $namespace);
        $namespace = ucfirst($namespace);
        $length = strlen($namespace);
        $i = 0;
        do {
            if ($i && $namespace[$i - 1] === '\\') {
                $namespace[$i] = strtoupper($namespace[$i]);
            }
            $i++;
        } while ($length >= $i);
        $replace[] = trim($namespace, '\\');


        if (array_key_exists('placeholders', $file)) {
            $placeholders = array_keys($file['placeholders']);
            $placeholders = array_map(fn($placeholder) => '{{ ' . $placeholder . ' }}', $placeholders);
            $search = array_merge($search, $placeholders);
            $replace = array_merge($replace, array_values($file['placeholders']));
        };

        return str_replace($search, $replace, $file['from']);
    }
}
