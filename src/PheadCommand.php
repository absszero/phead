<?php
namespace Absszero\Phead;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PheadCommand extends Command
{
    protected Layout $layout;

    protected function configure(): void
    {
        $this
        ->setName('phead')
        ->setDescription('Generate code by layout')
        ->addArgument('layout', InputArgument::REQUIRED, 'The layout to use.')
        ->addOption('dry', 'd', InputOption::VALUE_NONE, 'Dry run.')
        ->addOption('only', 'o', InputOption::VALUE_OPTIONAL, 'Only those file keys are generated. Separate by comma.')
        ->addOption('force', 'f', InputOption::VALUE_NONE, 'Ovverwrite existed files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('layout');
        if ($file === 'sample') {
            return $this->genSample($input, $output);
        }

        try {
            $file = $input->getArgument('layout');
            $this->layout = Layout::parse($file);
        } catch (ParseException $th) {
            $output->writeln('<error>'. $th->getMessage() .'</error>');
            return Command::FAILURE;
        }

        $cwd = getcwd();
        $output->writeln('');
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

        return Command::SUCCESS;
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

    /**
     * generate sample layout file
     *
     * @param   InputInterface   $input   [$input description]
     * @param   OutputInterface  $output  [$output description]
     *
     * @return  int                       [return description]
     */
    protected function genSample(InputInterface $input, OutputInterface $output): int
    {
        $target = getcwd() . '/my-layout.yaml';
        if (file_exists($target)) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<comment>my-layout.yaml</comment> <info>already exists. Replace current file?</info>', false);
            if (!$helper->ask($input, $output, $question)) {
                return Command::SUCCESS;
            }
        }

        $result = copy(__DIR__ . '/../config/sample.yaml', $target);
        if ($result) {
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
