<?php

declare(strict_types=1);

/*
 * This file is part of gpupo/common-schema
 * Created by Gilmar Pupo <contact@gpupo.com>
 * For the information of copyright and license you should read the file
 * LICENSE which is distributed with this source code.
 * Para a informação dos direitos autorais e de licença você deve ler o arquivo
 * LICENSE que é distribuído com este código-fonte.
 * Para obtener la información de los derechos de autor y la licencia debe leer
 * el archivo LICENSE que se distribuye con el código fuente.
 * For more information, see <https://opensource.gpupo.com/>.
 *
 */

namespace Gpupo\CommonSchema\Console\Command;

use Gpupo\Common\Traits\OptionsTrait;
use Gpupo\CommonSdk\Console\Command\AbstractCommand as Core;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class RaiseSubclassesCommand extends Core
{
    use OptionsTrait;

    protected $originNamespace = 'Gpupo\\CommonSchema\\Build';
    protected $originPath = 'build';

    public function getDefaultOptions()
    {
        return [
            'rootPath' => false,
            'libPath' => false,
            'namespace' => getenv('CS_RAISE_NAMESPACE'),
            'path' => getenv('CS_RAISE_PATH'),
        ];
    }

    protected function configure()
    {
        $this->setName('raise:build')->setDescription('Raise a namespace');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileSystem = new Filesystem();

        $this->buildSuperclasses($output);
        $output->writeln(sprintf('Check subclasses at <info>%s/build</>', $this->getOptions()->get('libPath')));

        $list = $this->find($this->getOptions()->get('libPath').'/build');
        $output->writeln(sprintf('Files to generate: <info>%d</>', \count($list)));

        foreach ($list as $origin) {
            $target = $this->factoryTarget($origin);

            if ($output->isVerbose()) {
                $output->writeln([
                    "\n",
                    'namespace:'.$target['namespace'],
                    'class:'.$target['class'],
                    'origin:'.$target['origin']['class'],
                    $target['path'],
                    $target['origin']['path'],
                    "\n",
                ]);
            }

            $this->save($input, $output, $target, $fileSystem);
        }

        $output->writeln('Done');

        return 0;
    }

    protected function save(InputInterface $input, OutputInterface $output, array $target, Filesystem $fileSystem)
    {
        $output->writeln(sprintf("\n* Build class %s", $target['class']));

        $string = 'File <fg=yellow>%s</> <fg=%s;bg=yellow> %s </>';

        if ($fileSystem->exists($target['path'])) {
            $output->writeln(sprintf($string, $target['path'], 'blue', 'exists'));

            $array = ['skip', 'skip-all', 'replace', 'replace-all'];
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Replace file? (defaults to skip)',
                $array,
                0
            );
            $question->setErrorMessage('Options %s is invalid.');

            $choice = $this->getOptions()->get('choice') ?? $helper->ask($input, $output, $question);

            if (\in_array($choice, [$array[1], $array[3]], true)) {
                $this->getOptions()->set('choice', $choice);
            }

            if (\in_array($choice, [$array[0], $array[1]], true)) {
                $output->writeln(sprintf($string, $target['path'], 'red', 'skipped'));

                return;
            }
        }

        $fileSystem->dumpFile($target['path'], $target['content']);
        $output->writeln(sprintf($string, $target['path'], 'blue', 'saved'));
    }

    protected function buildSuperclasses(OutputInterface $output): void
    {
        $command = './'.$this->getOptions()->get('libPath').'bin/build.sh ./'.$this->getDestPath().' '.$this->getOptions()->get('namespace');
        $output->writeln(sprintf('Excecuting [%s]', $command));
        shell_exec($command);
    }

    protected function getDestPath()
    {
        return $this->getOptions()->get('rootPath').'/'.$this->getOptions()->get('path');
    }

    protected function factoryTarget(array $origin): array
    {
        $explode = explode('\\', $origin['class']);
        $name = end($explode);
        $class = str_replace($this->originNamespace, $this->getOptions()->get('namespace'), $origin['class']);
        $target = [
            'class' => $class,
            'path' => str_replace($this->getOptions()->get('libPath').'/'.$this->originPath, $this->getDestPath(), $origin['path']),
            'name' => $name,
            'namespace' => rtrim(rtrim($class, $name), '\\'),
            'origin' => $origin,
        ];

        $template = <<<'EOF'
<?php

declare(strict_types=1);

/*
 * Generated by gpupo/common-schema
 * at %s
 */

namespace %s;

use %s as Superclass;
use Gpupo\CommonSchema\SubclassInterface;

/**
 * {@inheritDoc}
 * @internal
 */
final class %s extends Superclass implements SubclassInterface
{
    //put your custom code here
}

EOF;

        $target['content'] = sprintf($template, (new \DateTime())->format('Y-m-d H:i:s'), $target['namespace'], $origin['class'], $target['name']);

        return $target;
    }

    protected function find($path)
    {
        $finder = new Finder();
        $finder->sortByName()->files()->name('*.php')->notName('Abstract*')->notName('Factory*')
            ->notName('*Exception*')->notName('*Interface*')->in($path);

        $list = [];

        foreach ($finder as $file) {
            $content = file_get_contents($file->getPathName());
            $tokens = token_get_all($content);
            $namespace = '';
            for ($index = 0; isset($tokens[$index]); ++$index) {
                if (!isset($tokens[$index][0])) {
                    continue;
                }
                if (T_NAMESPACE === $tokens[$index][0]) {
                    $index += 2;
                    while (isset($tokens[$index]) && \is_array($tokens[$index])) {
                        $namespace .= $tokens[$index++][1];
                    }
                }
                if (T_CLASS === $tokens[$index][0]) {
                    $index += 2;

                    $classname = trim($namespace.'\\'.$tokens[$index][1]);

                    if ('\\' === substr($classname, -1) || array_key_exists($file->getPathName(), $list)) {
                        continue;
                    }

                    $list[$file->getPathName()] = [
                        'class' => $classname,
                        'path' => $file->getPathName(),
                    ];
                }
            }
        }

        return $list;
    }
}
