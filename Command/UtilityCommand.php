<?php

namespace DanielMaier\ConsoleUtility\Command;

use DanielMaier\ConsoleUtility\Console\Output;
use DanielMaier\ConsoleUtility\Helper\TimeMessureHelper;
use Magento\Framework\App\State;
use Magento\Framework\Data\Collection;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Option\ArrayInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

abstract class UtilityCommand extends Command
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var State
     */
    protected $state;

    /**
     * @var Question[]
     */
    protected $interactiveQuestsion = [];

    /**
     * @var array
     */
    protected $interactiveArguments = [];

    /**
     * @var OutputInterface|Output
     */
    protected $output;

    /**
     * @var InputInterface
     */
    protected $input;
    /**
     * @var TimeMessureHelper
     */
    private $timeMessureHelper;

    /**
     * AbstractCommand constructor.
     *
     * @param ObjectManagerInterface $objectManager
     * @param TimeMessureHelper $timeMessureHelper
     * @param State $state
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        TimeMessureHelper $timeMessureHelper,
        State $state
    )
    {
        parent::__construct();

        $this->objectManager = $objectManager;
        $this->state = $state;
        $this->timeMessureHelper = $timeMessureHelper;

        try {
            $this->state->setAreaCode('adminhtml');
        } catch (\Exception $exception) {

        }
    }

    /**
     * Configure Command
     *
     * @return void
     */
    abstract public function configureCommand();

    /**
     * Execute Command
     *
     * @return void
     */
    abstract public function executeCommand();

    /**
     * @return ObjectManagerInterface
     */
    public function getObjectManager()
    {
        return $this->objectManager;
    }

    /**
     * Force Abstract Implementation of Configure
     */
    protected function configure()
    {
        $this->configureCommand();

        foreach ($this->interactiveQuestsion as $argumentName => $question) {
            $this->addOption($argumentName, null, InputOption::VALUE_OPTIONAL, $question->getQuestion());
        }
    }

    /**
     * @param string $argumentName
     * @param Question $question
     */
    protected function addInteractiveQuestion($argumentName, $question)
    {
        if ($question->getDefault()) {
            $question = new Question(
                trim($question->getQuestion()) . ' [' . $question->getDefault() . '] ',
                $question->getDefault()
            );
        }

        $this->interactiveQuestsion[$argumentName] = $question;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = new Output($output);

        if (count($this->interactiveQuestsion) > 0) {
            foreach ($this->interactiveQuestsion as $argumentName => $question) {
                $givenValue = $this->input->getOption($argumentName);

                if (!empty($givenValue)) {
                    unset($this->interactiveQuestsion[$argumentName]);
                    $this->interactiveArguments[$argumentName] = $givenValue;
                }
            }

            $input->setInteractive(true);
        }

        $this->timeMessureHelper->start('full_command');

        $now = new \DateTime();

        $this->output->writeln('Running ' . $this->getName() . ' (' . $now->format('d.m.Y H:i') . ')');
        $this->output->writeln('');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (count($this->interactiveQuestsion) == 0) {
            return;
        }

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelperSet()->get('question');

        $this->output->logInfo('Please fill in the following to continue...');

        foreach ($this->interactiveQuestsion as $argumentName => $interactiveQuestion) {
            $this->interactiveArguments[$argumentName] = $questionHelper->ask(
                $this->input,
                $this->output,
                $interactiveQuestion
            );
        }

        $this->output->logEmpty();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->executeCommand();

            $this->output->writeln('');
            $this->output->logSuccess('All Done! -> Duration: ' . $this->timeMessureHelper->output('full_command'));
        } catch (\Exception $exception) {
            $this->output->logException($exception);
        }
    }

    /**
     * @param mixed|array|ArrayInterface|Collection $walkable
     * @param Callable $callback
     *
     * @throws \Exception
     */
    protected function walkProgress($walkable, $callback)
    {
        $isMultiPageCollection = ($walkable instanceof Collection && $walkable->getLastPageNumber() > 1);

        if ($isMultiPageCollection) {
            return $this->walkProgressMultiPage(
                $walkable,
                $callback
            );
        }

        $walkableCount = count($walkable);
        $walkableIndex = 0;

        $counterLength = strlen((string)$walkableCount);

        $serializedIndex = serialize(
            $counterLength . '_' . $walkableCount . '_' . microtime()
        );
        $serializedIndexFull = $serializedIndex . '_walkProgress_full';
        $serializedIndexRow = $serializedIndex . '_walkProgress_row';

        $this->timeMessureHelper->start($serializedIndexFull);

        foreach ($walkable as $item) {
            $this->timeMessureHelper->start($serializedIndexRow);

            $walkableIndex++;

            $currentCounter = str_pad($walkableIndex, $counterLength, '0', STR_PAD_LEFT);

            $this->output->write('|- ' . $currentCounter . ' / ' . $walkableCount . ' -> ');

            try {
                $result = $callback($item);

                $this->output->writeln('<comment>' . $result . '</comment> -> <info>' . $this->timeMessureHelper->output($serializedIndexRow) . '</info>');
            } catch (\Exception $exception) {
                $this->output->logException($exception);
                $this->output->logError($this->timeMessureHelper->output($serializedIndexRow));
            }
        }

        $this->output->writeln('');
        $this->output->writeln('<comment>Progress Done!</comment> -> <info>' . $this->timeMessureHelper->output($serializedIndexFull) . '</info>');
    }

    /**
     * @param Collection $walkableCollection
     * @param Callable $callback
     *
     * @throws \Exception
     */
    private function walkProgressMultiPage($walkableCollection, $callback)
    {
        $lastPage = $walkableCollection->getLastPageNumber();

        for ($currentPage = $walkableCollection->getCurPage(); $currentPage <= $lastPage; $currentPage++) {
            $walkableCollection->clear();
            $walkableCollection->setCurPage($currentPage);
            $walkableCollection->load();

            $walkableItems = $walkableCollection->getItems();

            if (count($walkableItems) == 0) {
                throw new \Exception('Failed to retrieve Collection Page...');
            }

            $this->walkProgress($walkableItems, function ($walkableItem) use ($currentPage, $lastPage, $callback) {
                return 'Page ' . $currentPage . ' of ' . $lastPage . ' -> ' . $callback($walkableItem);
            });
        }
    }
}