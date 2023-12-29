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

    protected function configure(): void
    {
        $this
        ->setName('head')
        ->setDescription('Generate code by layout')
        ->addArgument('layout', InputArgument::REQUIRED, 'The layout to use.')
        ->addOption('dry', 'd', InputOption::VALUE_NONE, 'Dry run.')
        ->addOption('only', 'o', InputOption::VALUE_OPTIONAL, 'Only those file keys are generated. Separate by comma.')
        ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force ovverwrite existed files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $file = $input->getArgument('layout');
            $this->layout = Layout::parse($file);
        } catch (ParseException $th) {
            $output->writeln('<error>'. $th->getMessage() .'</error>');
            return 1;
        }

        $cwd = getcwd();
        $output->write('<info>Generating files...</info>');
        $text = '';
        if ($input->getOption('dry')) {
            $text = '<comment> (dry run) </comment>';
        }
        if ($input->getOption('force')) {
            $text = '<fg=red> (force) </>';
        }
        $output->writeln($text);

        $files = $this->layout->data['$files'];
        $files = $this->getOnlyFiles($files, $input->getOption('only'));
        foreach ($files as $file) {
            $path = $cwd . '/' . $file['to'];
            $output->write('<info>' . $file['to'] . '</info>');

            $skip = $input->getOption('dry') || ($file['skip'] ?? false);
            if ($skip) {
                $output->writeln('<comment> (skip) </comment>');
                continue;
            }

            $text = '';
            if (file_exists($file['to'])) {
                if (!$input->getOption('force')) {
                    $output->writeln('');
                    continue;
                }
                $text = '<fg=red> (overwrite) </>';
            }
            $output->writeln($text);

            $dir = dirname($path);
            !is_dir($dir) && mkdir($dir, 0777, true);
            file_put_contents($path, $file['from']);
        }

        return 0;
    }

    /**
     * Get files only in the $only
     *
     * @param   array<string, mixed>   $files  [$files description]
     * @param   string  $only                  [$only description]
     *
     * @return  array<string, mixed>           [return description]
     */
    protected function getOnlyFiles(array $files, ?string $only): array
    {
        if (!$only) {
            return $files;
        }

        $only = explode(',', $only);
        $only = array_filter($only);
        $only = array_map('trim', $only);

        $found = [];
        foreach ($only as $fileKey) {
            if (isset($files[$fileKey])) {
                // ignore the skip flag
                $files[$fileKey]['skip'] = false;
                $found[$fileKey] = $files[$fileKey];
            }
        }

        return $found;
    }
}
